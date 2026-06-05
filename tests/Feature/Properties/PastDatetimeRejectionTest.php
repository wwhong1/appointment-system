<?php

namespace Tests\Feature\Properties;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 8: Past start datetime rejection
 *
 * Validates: Requirements 5.6, 13.7
 *
 * For any start datetime that is before the current time at the moment of submission,
 * the system SHALL reject the appointment creation or update.
 */
class PastDatetimeRejectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private Service $service;
    private AppointmentService $appointmentService;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();
        $this->appointmentService = app(AppointmentService::class);

        // Fix "now" to a known point for deterministic testing
        Carbon::setTestNow(Carbon::parse('2025-06-15 12:00:00', 'UTC'));

        // Set up branch with wide operating hours (00:00-23:59 UTC) to avoid
        // operating hours failures interfering with past datetime tests
        $this->branch = Branch::create([
            'name' => 'Past Datetime Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'Past Datetime Test Staff',
            'email' => 'past-datetime-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Past Datetime Test Customer',
            'email' => 'past-datetime-customer@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'Past Datetime Test Service',
            'duration_minutes' => 30,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow(); // Reset test time
        parent::tearDown();
    }

    /**
     * Property test: Any past start datetime (1 minute to years in the past)
     * should be rejected on appointment creation.
     */
    public function test_property_past_start_datetime_rejected_on_create(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Generate a random past datetime: 1 minute to 5 years in the past
            $minutesInPast = $this->faker->numberBetween(1, 2628000); // ~5 years in minutes
            $pastDatetime = Carbon::now()->subMinutes($minutesInPast);

            $data = [
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $pastDatetime,
            ];

            $rejected = false;
            try {
                $this->appointmentService->create($data);
            } catch (ValidationException $e) {
                $rejected = true;
                $this->assertArrayHasKey(
                    'start_datetime',
                    $e->errors(),
                    "Iteration {$i}: ValidationException should target start_datetime field."
                );
            }

            $this->assertTrue(
                $rejected,
                "Iteration {$i}: Past datetime ({$pastDatetime->toIso8601String()}, "
                . "{$minutesInPast} minutes in the past) should be rejected on create."
            );
        }
    }

    /**
     * Property test: Any future start datetime (within operating hours)
     * should be accepted on appointment creation.
     */
    public function test_property_future_start_datetime_accepted_on_create(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Generate a random future datetime: 1 minute to 365 days in the future
            $minutesInFuture = $this->faker->numberBetween(1, 525600); // 1 year in minutes
            $futureDatetime = Carbon::now()->addMinutes($minutesInFuture);

            // Ensure the appointment fits within operating hours (00:00-23:59 UTC)
            // Adjust to start at a time where start + 30 min service won't exceed 23:59
            $futureDatetime->setTime(
                $this->faker->numberBetween(0, 23),
                $this->faker->numberBetween(0, 28), // max 28 so end (+ 30 min) stays within 23:59
                0
            );

            // Make sure it's still in the future after time adjustment
            if ($futureDatetime->isPast()) {
                $futureDatetime->addDay();
            }

            $data = [
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $futureDatetime,
            ];

            try {
                $appointment = $this->appointmentService->create($data);
                $this->assertNotNull(
                    $appointment->id,
                    "Iteration {$i}: Future datetime should create an appointment."
                );
            } catch (ValidationException $e) {
                // If it fails, it should NOT be because of past datetime
                $errors = $e->errors();
                $this->assertFalse(
                    isset($errors['start_datetime']) &&
                    str_contains($errors['start_datetime'][0] ?? '', 'must be in the future'),
                    "Iteration {$i}: Future datetime ({$futureDatetime->toIso8601String()}) "
                    . "should not be rejected as past. Errors: " . json_encode($errors)
                );
            }

            // Clean up to avoid overlap issues in next iteration
            \App\Models\Appointment::query()->delete();
        }
    }

    /**
     * Property test: Updating an appointment's start_datetime to a past value
     * should be rejected.
     */
    public function test_property_past_start_datetime_rejected_on_update(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Clean up from previous iteration
            \App\Models\Appointment::query()->delete();

            // Create a valid appointment in the future first
            $futureStart = Carbon::now()->addHours(2);
            $futureStart->setTime(10, 0, 0); // 10:00 AM to ensure within operating hours
            if ($futureStart->isPast()) {
                $futureStart->addDay();
            }

            $appointment = $this->appointmentService->create([
                'branch_id' => $this->branch->id,
                'staff_id' => $this->staff->id,
                'customer_id' => $this->customer->id,
                'service_id' => $this->service->id,
                'start_datetime' => $futureStart,
            ]);

            // Now try to update it to a past datetime
            $minutesInPast = $this->faker->numberBetween(1, 2628000);
            $pastDatetime = Carbon::now()->subMinutes($minutesInPast);

            $rejected = false;
            try {
                $this->appointmentService->update($appointment, [
                    'start_datetime' => $pastDatetime,
                ]);
            } catch (ValidationException $e) {
                $rejected = true;
                $this->assertArrayHasKey(
                    'start_datetime',
                    $e->errors(),
                    "Iteration {$i}: ValidationException on update should target start_datetime field."
                );
            }

            $this->assertTrue(
                $rejected,
                "Iteration {$i}: Updating to past datetime ({$pastDatetime->toIso8601String()}, "
                . "{$minutesInPast} minutes in the past) should be rejected."
            );
        }
    }
}
