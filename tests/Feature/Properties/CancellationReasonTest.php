<?php

namespace Tests\Feature\Properties;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
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
 * Property 13: Cancellation reason length validation
 *
 * Validates: Requirements 9.3, 9.4
 *
 * For any status transition to cancelled, the system SHALL require a cancellation_reason
 * string with length in [1, 500] characters, and SHALL reject the transition if the reason
 * is absent or outside this length range.
 */
class CancellationReasonTest extends TestCase
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

        $this->branch = Branch::create([
            'name' => 'Cancellation Reason Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'Cancellation Reason Test Staff',
            'email' => 'cancellation-reason-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Cancellation Reason Test Customer',
            'email' => 'cancellation-reason-customer@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'Cancellation Reason Test Service',
            'duration_minutes' => 30,
        ]);

        $this->appointmentService = new AppointmentService();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * Helper: Create a fresh appointment with the given status that can transition to cancelled.
     *
     * @param AppointmentStatus $status Must be 'pending' or 'confirmed'
     * @return Appointment
     */
    private function createAppointmentWithStatus(AppointmentStatus $status): Appointment
    {
        $now = Carbon::create(2025, 6, 1, 0, 0, 0, 'UTC');
        Carbon::setTestNow($now);

        // Generate a start time in the future that fits within operating hours
        $startDatetime = $now->copy()->addDays($this->faker->numberBetween(1, 30))
            ->setHour($this->faker->numberBetween(0, 22))
            ->setMinute($this->faker->numberBetween(0, 29))
            ->setSecond(0);

        $appointment = $this->appointmentService->create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => $startDatetime,
        ]);

        // If we need confirmed status, transition from pending to confirmed
        if ($status === AppointmentStatus::Confirmed) {
            $appointment = $this->appointmentService->transitionStatus(
                $appointment,
                AppointmentStatus::Confirmed
            );
        }

        return $appointment;
    }

    /**
     * Helper: Generate a random string of exact length.
     */
    private function generateStringOfLength(int $length): string
    {
        if ($length <= 0) {
            return '';
        }

        $result = '';
        while (mb_strlen($result) < $length) {
            $result .= $this->faker->sentence();
        }

        return mb_substr($result, 0, $length);
    }

    /**
     * Property 13: Valid cancellation reasons (1-500 chars) should be accepted.
     *
     * For any random string with length in [1, 500], the system SHALL accept
     * the transition to cancelled when the reason is provided.
     *
     * **Validates: Requirements 9.3**
     */
    public function test_property_valid_reasons_accepted_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            // Clean up appointments between iterations to avoid overlap conflicts
            Appointment::query()->delete();

            // Pick a random status that can transition to cancelled
            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            // Generate a random reason with length between 1 and 500
            $reasonLength = $this->faker->numberBetween(1, 500);
            $reason = $this->generateStringOfLength($reasonLength);

            $result = $this->appointmentService->transitionStatus(
                $appointment,
                AppointmentStatus::Cancelled,
                $reason
            );

            $this->assertEquals(
                AppointmentStatus::Cancelled,
                $result->status,
                "Iteration {$i}: Transition to cancelled with valid reason (length={$reasonLength}) "
                . "from '{$fromStatus->value}' should be accepted."
            );

            $this->assertEquals(
                $reason,
                $result->cancellation_reason,
                "Iteration {$i}: Cancellation reason should be stored correctly."
            );
        }
    }

    /**
     * Property 13: Null/missing cancellation reason should be rejected.
     *
     * For any appointment transitioning to cancelled with a null reason,
     * the system SHALL reject the transition.
     *
     * **Validates: Requirements 9.4**
     */
    public function test_property_null_reason_rejected_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            try {
                $this->appointmentService->transitionStatus(
                    $appointment,
                    AppointmentStatus::Cancelled,
                    null
                );

                $this->fail(
                    "Iteration {$i}: Transition to cancelled with null reason "
                    . "from '{$fromStatus->value}' should have been rejected."
                );
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(
                    'cancellation_reason',
                    $e->errors(),
                    "Iteration {$i}: Validation error should reference 'cancellation_reason' field."
                );
            }

            // Verify status was not changed
            $appointment->refresh();
            $this->assertEquals(
                $fromStatus,
                $appointment->status,
                "Iteration {$i}: Appointment status should remain '{$fromStatus->value}' after rejection."
            );
        }
    }

    /**
     * Property 13: Empty string (0 chars) cancellation reason should be rejected.
     *
     * For any appointment transitioning to cancelled with an empty string reason,
     * the system SHALL reject the transition.
     *
     * **Validates: Requirements 9.4**
     */
    public function test_property_empty_string_reason_rejected_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            try {
                $this->appointmentService->transitionStatus(
                    $appointment,
                    AppointmentStatus::Cancelled,
                    ''
                );

                $this->fail(
                    "Iteration {$i}: Transition to cancelled with empty string reason "
                    . "from '{$fromStatus->value}' should have been rejected."
                );
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(
                    'cancellation_reason',
                    $e->errors(),
                    "Iteration {$i}: Validation error should reference 'cancellation_reason' field."
                );
            }

            // Verify status was not changed
            $appointment->refresh();
            $this->assertEquals(
                $fromStatus,
                $appointment->status,
                "Iteration {$i}: Appointment status should remain '{$fromStatus->value}' after rejection."
            );
        }
    }

    /**
     * Property 13: Reasons exceeding 500 characters should be rejected.
     *
     * For any random string with length > 500, the system SHALL reject
     * the transition to cancelled.
     *
     * **Validates: Requirements 9.4**
     */
    public function test_property_reason_exceeding_500_chars_rejected_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            // Generate a reason with length between 501 and 1000
            $reasonLength = $this->faker->numberBetween(501, 1000);
            $reason = $this->generateStringOfLength($reasonLength);

            try {
                $this->appointmentService->transitionStatus(
                    $appointment,
                    AppointmentStatus::Cancelled,
                    $reason
                );

                $this->fail(
                    "Iteration {$i}: Transition to cancelled with reason length={$reasonLength} "
                    . "from '{$fromStatus->value}' should have been rejected."
                );
            } catch (ValidationException $e) {
                $this->assertArrayHasKey(
                    'cancellation_reason',
                    $e->errors(),
                    "Iteration {$i}: Validation error should reference 'cancellation_reason' field."
                );
            }

            // Verify status was not changed
            $appointment->refresh();
            $this->assertEquals(
                $fromStatus,
                $appointment->status,
                "Iteration {$i}: Appointment status should remain '{$fromStatus->value}' after rejection."
            );
        }
    }

    /**
     * Property 13: Boundary test - reason of exactly 1 character should be accepted.
     *
     * For any appointment transitioning to cancelled with a single-character reason,
     * the system SHALL accept the transition.
     *
     * **Validates: Requirements 9.3**
     */
    public function test_property_boundary_1_char_reason_accepted_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            // Generate a single random character
            $reason = $this->faker->randomLetter();

            $result = $this->appointmentService->transitionStatus(
                $appointment,
                AppointmentStatus::Cancelled,
                $reason
            );

            $this->assertEquals(
                AppointmentStatus::Cancelled,
                $result->status,
                "Iteration {$i}: Transition to cancelled with 1-char reason "
                . "from '{$fromStatus->value}' should be accepted."
            );

            $this->assertEquals(
                $reason,
                $result->cancellation_reason,
                "Iteration {$i}: Single-character cancellation reason should be stored correctly."
            );
        }
    }

    /**
     * Property 13: Boundary test - reason of exactly 500 characters should be accepted.
     *
     * For any appointment transitioning to cancelled with a 500-character reason,
     * the system SHALL accept the transition.
     *
     * **Validates: Requirements 9.3**
     */
    public function test_property_boundary_500_char_reason_accepted_100_iterations(): void
    {
        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $fromStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ]);

            $appointment = $this->createAppointmentWithStatus($fromStatus);

            // Generate a reason of exactly 500 characters
            $reason = $this->generateStringOfLength(500);

            $result = $this->appointmentService->transitionStatus(
                $appointment,
                AppointmentStatus::Cancelled,
                $reason
            );

            $this->assertEquals(
                AppointmentStatus::Cancelled,
                $result->status,
                "Iteration {$i}: Transition to cancelled with 500-char reason "
                . "from '{$fromStatus->value}' should be accepted."
            );

            $this->assertEquals(
                $reason,
                $result->cancellation_reason,
                "Iteration {$i}: 500-character cancellation reason should be stored correctly."
            );
        }
    }
}
