<?php

namespace Tests\Feature\Properties;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 7: End datetime derived from start plus service duration
 *
 * Validates: Requirements 5.1, 11.8
 *
 * For any appointment with a start datetime and a service with duration D minutes,
 * the system SHALL set end_datetime = start_datetime + D minutes, and this invariant
 * SHALL hold after both creation and update.
 */
class EndDatetimeDerivationTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private AppointmentService $appointmentService;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        // Use wide operating hours to avoid validation failures during random testing
        $this->branch = Branch::create([
            'name' => 'End Datetime Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'End Datetime Test Staff',
            'email' => 'end-datetime-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'End Datetime Test Customer',
            'email' => 'end-datetime-customer@example.com',
        ]);

        $this->appointmentService = new AppointmentService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Property test: On creation, end_datetime = start_datetime + service duration_minutes
     * for any random start datetime and any random service duration (1-480 minutes).
     */
    public function test_property_end_datetime_equals_start_plus_duration_on_creation(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Clean up appointments between iterations to avoid overlap conflicts
            Appointment::query()->delete();
            Service::query()->delete();

            // Generate random service duration between 1 and 480 minutes
            $durationMinutes = $this->faker->numberBetween(1, 480);

            $service = Service::create([
                'name' => "Service Iteration {$i}",
                'duration_minutes' => $durationMinutes,
            ]);

            // Generate a random start datetime in the future
            // Set "now" to a fixed point so we can generate future datetimes reliably
            $now = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Random start: between 1 minute and 30 days in the future, at minute 0 to stay within operating hours
            $minutesInFuture = $this->faker->numberBetween(1, 43200); // up to 30 days
            $startDatetime = $now->copy()->addMinutes($minutesInFuture);

            // Ensure start time is within operating hours (00:00 - 23:59)
            // and end time won't exceed closing time
            $startHour = $this->faker->numberBetween(0, 23);
            $startMinute = $this->faker->numberBetween(0, 59);
            $startDatetime = $now->copy()->addDays($this->faker->numberBetween(1, 30))
                ->setHour($startHour)
                ->setMinute($startMinute)
                ->setSecond(0);

            // Ensure end time (start + duration) doesn't exceed 23:59
            $endMinutesOfDay = ($startHour * 60 + $startMinute) + $durationMinutes;
            if ($endMinutesOfDay > 23 * 60 + 59) {
                // Adjust start time so end fits within operating hours
                $maxStartMinutes = (23 * 60 + 59) - $durationMinutes;
                if ($maxStartMinutes < 0) {
                    $maxStartMinutes = 0;
                }
                $adjustedMinutes = $this->faker->numberBetween(0, max(0, $maxStartMinutes));
                $startDatetime = $startDatetime->copy()
                    ->setHour(intdiv($adjustedMinutes, 60))
                    ->setMinute($adjustedMinutes % 60)
                    ->setSecond(0);
            }

            $expectedEnd = $startDatetime->copy()->addMinutes($durationMinutes);

            $appointment = $this->appointmentService->create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $service->id,
                'start_datetime' => $startDatetime,
            ]);

            $this->assertTrue(
                $appointment->end_datetime->eq($expectedEnd),
                "Iteration {$i}: end_datetime should equal start + duration. "
                . "Start: {$startDatetime}, Duration: {$durationMinutes}min, "
                . "Expected end: {$expectedEnd}, Actual end: {$appointment->end_datetime}"
            );
        }
    }

    /**
     * Property test: On update (changing start_datetime), end_datetime is recalculated
     * as new_start + service duration.
     */
    public function test_property_end_datetime_recalculated_on_start_change(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();
            Service::query()->delete();

            $durationMinutes = $this->faker->numberBetween(1, 480);

            $service = Service::create([
                'name' => "Service Update Start {$i}",
                'duration_minutes' => $durationMinutes,
            ]);

            $now = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Create initial appointment with a valid start time
            $initialStart = $this->generateValidStart($now, $durationMinutes);
            $appointment = $this->appointmentService->create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $service->id,
                'start_datetime' => $initialStart,
            ]);

            // Generate a new random start datetime (different from initial)
            $newStart = $this->generateValidStart($now, $durationMinutes);

            $expectedEnd = $newStart->copy()->addMinutes($durationMinutes);

            $updatedAppointment = $this->appointmentService->update($appointment, [
                'start_datetime' => $newStart,
            ]);

            $this->assertTrue(
                $updatedAppointment->end_datetime->eq($expectedEnd),
                "Iteration {$i}: After start change, end_datetime should equal new_start + duration. "
                . "New start: {$newStart}, Duration: {$durationMinutes}min, "
                . "Expected end: {$expectedEnd}, Actual end: {$updatedAppointment->end_datetime}"
            );
        }
    }

    /**
     * Property test: On update (changing service), end_datetime is recalculated
     * as start + new_service_duration.
     */
    public function test_property_end_datetime_recalculated_on_service_change(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();
            Service::query()->delete();

            // Create two services with different durations
            $originalDuration = $this->faker->numberBetween(1, 240);
            $newDuration = $this->faker->numberBetween(1, 480);

            $originalService = Service::create([
                'name' => "Original Service {$i}",
                'duration_minutes' => $originalDuration,
            ]);

            $newService = Service::create([
                'name' => "New Service {$i}",
                'duration_minutes' => $newDuration,
            ]);

            $now = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Use the max of both durations to ensure start time works for both
            $maxDuration = max($originalDuration, $newDuration);
            $startDatetime = $this->generateValidStart($now, $maxDuration);

            $appointment = $this->appointmentService->create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $originalService->id,
                'start_datetime' => $startDatetime,
            ]);

            // Verify initial end_datetime
            $expectedInitialEnd = $startDatetime->copy()->addMinutes($originalDuration);
            $this->assertTrue(
                $appointment->end_datetime->eq($expectedInitialEnd),
                "Iteration {$i}: Initial end_datetime should be start + original duration."
            );

            // Update to new service
            $expectedNewEnd = $startDatetime->copy()->addMinutes($newDuration);

            $updatedAppointment = $this->appointmentService->update($appointment, [
                'service_id' => $newService->id,
            ]);

            $this->assertTrue(
                $updatedAppointment->end_datetime->eq($expectedNewEnd),
                "Iteration {$i}: After service change, end_datetime should equal start + new_duration. "
                . "Start: {$startDatetime}, New duration: {$newDuration}min, "
                . "Expected end: {$expectedNewEnd}, Actual end: {$updatedAppointment->end_datetime}"
            );
        }
    }

    /**
     * Property test: On update (changing both start and service), end_datetime is
     * recalculated as new_start + new_service_duration.
     */
    public function test_property_end_datetime_recalculated_on_both_start_and_service_change(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();
            Service::query()->delete();

            $originalDuration = $this->faker->numberBetween(1, 240);
            $newDuration = $this->faker->numberBetween(1, 480);

            $originalService = Service::create([
                'name' => "Original Both {$i}",
                'duration_minutes' => $originalDuration,
            ]);

            $newService = Service::create([
                'name' => "New Both {$i}",
                'duration_minutes' => $newDuration,
            ]);

            $now = Carbon::create(2025, 1, 1, 0, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            $initialStart = $this->generateValidStart($now, $originalDuration);

            $appointment = $this->appointmentService->create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $originalService->id,
                'start_datetime' => $initialStart,
            ]);

            // Generate new start that works with new duration
            $newStart = $this->generateValidStart($now, $newDuration);
            $expectedEnd = $newStart->copy()->addMinutes($newDuration);

            $updatedAppointment = $this->appointmentService->update($appointment, [
                'start_datetime' => $newStart,
                'service_id' => $newService->id,
            ]);

            $this->assertTrue(
                $updatedAppointment->end_datetime->eq($expectedEnd),
                "Iteration {$i}: After both changes, end_datetime should equal new_start + new_duration. "
                . "New start: {$newStart}, New duration: {$newDuration}min, "
                . "Expected end: {$expectedEnd}, Actual end: {$updatedAppointment->end_datetime}"
            );
        }
    }

    /**
     * Helper: Generate a valid start datetime that ensures the appointment
     * (start + duration) fits within operating hours (00:00 - 23:59).
     */
    private function generateValidStart(Carbon $now, int $durationMinutes): Carbon
    {
        // Operating hours: 00:00 - 23:59 (in minutes: 0 - 1439)
        $maxStartMinutes = (23 * 60 + 59) - $durationMinutes;
        if ($maxStartMinutes < 0) {
            $maxStartMinutes = 0;
        }

        $startMinutesOfDay = $this->faker->numberBetween(0, max(0, $maxStartMinutes));
        $daysInFuture = $this->faker->numberBetween(1, 30);

        return $now->copy()
            ->addDays($daysInFuture)
            ->setHour(intdiv($startMinutesOfDay, 60))
            ->setMinute($startMinutesOfDay % 60)
            ->setSecond(0);
    }
}
