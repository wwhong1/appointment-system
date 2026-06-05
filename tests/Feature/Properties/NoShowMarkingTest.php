<?php

namespace Tests\Feature\Properties;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 14: Automatic no-show marking
 *
 * Validates: Requirements 10.1, 10.2
 *
 * For any appointment with status confirmed whose start_datetime is more than
 * 15 minutes in the past, the scheduled no-show command SHALL transition its
 * status to no_show. Appointments with any other status SHALL NOT be transitioned.
 */
class NoShowMarkingTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private Service $service;
    private \Faker\Generator $faker;

    /**
     * Non-confirmed statuses that should never be marked as no-show.
     */
    private const NON_CONFIRMED_STATUSES = [
        'pending',
        'in-progress',
        'completed',
        'cancelled',
        'no_show',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        $this->branch = Branch::create([
            'name' => 'No-Show Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'No-Show Test Staff',
            'email' => 'noshow-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'No-Show Test Customer',
            'email' => 'noshow-customer@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'No-Show Test Service',
            'duration_minutes' => 60,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Property 14: Confirmed appointments more than 15 minutes past start are marked no_show.
     *
     * For any confirmed appointment with start_datetime more than 15 minutes in the past
     * (randomly generated offset between 16 and 120 minutes), the mark-no-show command
     * SHALL transition its status to no_show.
     *
     * **Validates: Requirements 10.1, 10.2**
     */
    public function test_property_confirmed_appointments_past_15_minutes_are_marked_no_show(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            // Fix "now" to a known time
            $now = Carbon::create(2025, 6, 15, 12, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Generate a random offset between 16 and 120 minutes in the past
            $minutesPast = $this->faker->numberBetween(16, 120);
            $startDatetime = $now->copy()->subMinutes($minutesPast);

            $appointment = Appointment::create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $startDatetime,
                'end_datetime' => $startDatetime->copy()->addMinutes($this->service->duration_minutes),
                'status' => AppointmentStatus::Confirmed->value,
            ]);

            $this->artisan('appointments:mark-no-show')->assertSuccessful();

            $appointment->refresh();

            $this->assertEquals(
                AppointmentStatus::NoShow,
                $appointment->status,
                "Iteration {$i}: Confirmed appointment with start_datetime {$minutesPast} minutes in the past "
                . "(start: {$startDatetime->toDateTimeString()}, now: {$now->toDateTimeString()}) "
                . "should be marked as no_show but status is '{$appointment->status->value}'"
            );
        }
    }

    /**
     * Property 14: Confirmed appointments within 15 minutes are NOT marked no_show.
     *
     * For any confirmed appointment with start_datetime within 15 minutes of now
     * (randomly generated offset between 0 and 14 minutes in the past, or in the future),
     * the mark-no-show command SHALL NOT transition its status.
     *
     * **Validates: Requirements 10.1, 10.2**
     */
    public function test_property_confirmed_appointments_within_15_minutes_are_not_marked(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            // Fix "now" to a known time
            $now = Carbon::create(2025, 6, 15, 12, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Generate a random offset between 0 and 14 minutes in the past
            $minutesPast = $this->faker->numberBetween(0, 14);
            $startDatetime = $now->copy()->subMinutes($minutesPast);

            $appointment = Appointment::create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $startDatetime,
                'end_datetime' => $startDatetime->copy()->addMinutes($this->service->duration_minutes),
                'status' => AppointmentStatus::Confirmed->value,
            ]);

            $this->artisan('appointments:mark-no-show')->assertSuccessful();

            $appointment->refresh();

            $this->assertEquals(
                AppointmentStatus::Confirmed,
                $appointment->status,
                "Iteration {$i}: Confirmed appointment with start_datetime only {$minutesPast} minutes in the past "
                . "(start: {$startDatetime->toDateTimeString()}, now: {$now->toDateTimeString()}) "
                . "should NOT be marked as no_show but status is '{$appointment->status->value}'"
            );
        }
    }

    /**
     * Property 14: Non-confirmed appointments are never marked no_show regardless of time.
     *
     * For any appointment with a status other than confirmed (pending, in-progress,
     * completed, cancelled, no_show) and a start_datetime more than 15 minutes in the past,
     * the mark-no-show command SHALL NOT transition its status.
     *
     * **Validates: Requirements 10.1, 10.2**
     */
    public function test_property_non_confirmed_appointments_are_never_marked_no_show(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            Appointment::query()->delete();

            // Fix "now" to a known time
            $now = Carbon::create(2025, 6, 15, 12, 0, 0, 'UTC');
            Carbon::setTestNow($now);

            // Generate a random offset well past 15 minutes (16-120 minutes)
            $minutesPast = $this->faker->numberBetween(16, 120);
            $startDatetime = $now->copy()->subMinutes($minutesPast);

            // Randomly select a non-confirmed status
            $statusValue = $this->faker->randomElement(self::NON_CONFIRMED_STATUSES);
            $expectedStatus = AppointmentStatus::from($statusValue);

            $appointment = Appointment::create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $startDatetime,
                'end_datetime' => $startDatetime->copy()->addMinutes($this->service->duration_minutes),
                'status' => $statusValue,
            ]);

            $this->artisan('appointments:mark-no-show')->assertSuccessful();

            $appointment->refresh();

            $this->assertEquals(
                $expectedStatus,
                $appointment->status,
                "Iteration {$i}: Appointment with status '{$statusValue}' and start_datetime {$minutesPast} minutes "
                . "in the past should NOT be marked as no_show but status changed to '{$appointment->status->value}'"
            );
        }
    }
}
