<?php

namespace Tests\Feature\Properties;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Rules\NoOverlapRule;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 11: Appointment overlap detection
 *
 * Validates: Requirements 8.1, 8.2, 8.3, 8.4, 8.5
 *
 * For any staff member with existing active appointments (status in {pending, confirmed,
 * in-progress, completed}), and a new or updated appointment time period [start, end),
 * the system SHALL reject the appointment if and only if there exists an active appointment
 * (excluding self on update) whose start is before the new end AND whose end is after the
 * new start. Adjacent appointments (existing.end == new.start) SHALL be treated as
 * non-overlapping.
 */
class OverlapDetectionTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private Service $service;
    private \Faker\Generator $faker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->faker = Faker::create();

        $this->branch = Branch::create([
            'name' => 'Property Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'UTC',
            'opening_time' => '00:00:00',
            'closing_time' => '23:59:00',
        ]);

        $this->staff = User::create([
            'name' => 'Property Test Staff',
            'email' => 'property-staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Property Test Customer',
            'email' => 'property-customer@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'Property Test Service',
            'duration_minutes' => 60,
        ]);
    }

    /**
     * Helper: create an appointment for the staff member.
     */
    private function createAppointment(
        Carbon $start,
        Carbon $end,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
    ): Appointment {
        return Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => $start,
            'end_datetime' => $end,
            'status' => $status->value,
        ]);
    }

    /**
     * Helper: run the NoOverlapRule and return whether it passed (no errors).
     */
    private function rulePassesFor(
        Carbon $start,
        Carbon $end,
        ?int $excludeAppointmentId = null,
    ): bool {
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: $start,
            endDatetime: $end,
            excludeAppointmentId: $excludeAppointmentId,
        );

        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('start_datetime', null, $fail);

        return empty($errors);
    }

    /**
     * Helper: generate a random time period [start, end) with positive duration.
     *
     * @return array{start: Carbon, end: Carbon}
     */
    private function randomTimePeriod(): array
    {
        $baseDate = Carbon::parse('2025-06-01 00:00:00', 'UTC');
        $startOffset = $this->faker->numberBetween(0, 1440 * 7); // within a week in minutes
        $duration = $this->faker->numberBetween(15, 240); // 15 min to 4 hours

        $start = $baseDate->copy()->addMinutes($startOffset);
        $end = $start->copy()->addMinutes($duration);

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Helper: determine if two time periods overlap using the formal definition.
     * Overlap: existing.start < new.end AND existing.end > new.start
     * Adjacent (existing.end == new.start) is NOT overlapping.
     */
    private function periodsOverlap(
        Carbon $existingStart,
        Carbon $existingEnd,
        Carbon $newStart,
        Carbon $newEnd,
    ): bool {
        return $existingStart->lt($newEnd) && $existingEnd->gt($newStart);
    }

    /**
     * Property test: Non-overlapping appointments (new is completely before existing)
     * should always be accepted.
     */
    public function test_property_non_overlapping_before_always_accepted(): void
    {
        $baseDate = Carbon::parse('2025-06-15 12:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            // Clean up appointments between iterations
            Appointment::query()->delete();

            // Create existing appointment with random duration
            $existingDuration = $this->faker->numberBetween(15, 240);
            $existingStart = $baseDate->copy();
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // New appointment completely before existing (gap > 0)
            $newDuration = $this->faker->numberBetween(15, 120);
            $gap = $this->faker->numberBetween(1, 120); // at least 1 minute gap
            $newEnd = $existingStart->copy()->subMinutes($gap);
            $newStart = $newEnd->copy()->subMinutes($newDuration);

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Non-overlapping (before) should be accepted. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Non-overlapping appointments (new is completely after existing)
     * should always be accepted.
     */
    public function test_property_non_overlapping_after_always_accepted(): void
    {
        $baseDate = Carbon::parse('2025-06-15 08:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(15, 240);
            $existingStart = $baseDate->copy();
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // New appointment completely after existing (gap > 0)
            $newDuration = $this->faker->numberBetween(15, 120);
            $gap = $this->faker->numberBetween(1, 120);
            $newStart = $existingEnd->copy()->addMinutes($gap);
            $newEnd = $newStart->copy()->addMinutes($newDuration);

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Non-overlapping (after) should be accepted. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Adjacent appointments (existing.end == new.start) should be
     * treated as non-overlapping and accepted.
     */
    public function test_property_adjacent_appointments_always_accepted(): void
    {
        $baseDate = Carbon::parse('2025-06-15 09:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(15, 240);
            $existingStart = $baseDate->copy()->addMinutes($this->faker->numberBetween(0, 480));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // New appointment starts exactly when existing ends (adjacent)
            $newDuration = $this->faker->numberBetween(15, 120);
            $newStart = $existingEnd->copy();
            $newEnd = $newStart->copy()->addMinutes($newDuration);

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Adjacent (existing.end == new.start) should be accepted. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Adjacent appointments (new.end == existing.start) should be
     * treated as non-overlapping and accepted.
     */
    public function test_property_adjacent_reverse_appointments_always_accepted(): void
    {
        $baseDate = Carbon::parse('2025-06-15 12:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(15, 240);
            $existingStart = $baseDate->copy()->addMinutes($this->faker->numberBetween(60, 480));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // New appointment ends exactly when existing starts (adjacent)
            $newDuration = $this->faker->numberBetween(15, 60);
            $newEnd = $existingStart->copy();
            $newStart = $newEnd->copy()->subMinutes($newDuration);

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Adjacent (new.end == existing.start) should be accepted. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Overlapping appointments (partial overlap, contained, containing)
     * should always be rejected.
     */
    public function test_property_overlapping_appointments_always_rejected(): void
    {
        $baseDate = Carbon::parse('2025-06-15 10:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(30, 180);
            $existingStart = $baseDate->copy();
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // Generate a new appointment that definitely overlaps
            $overlapType = $this->faker->randomElement(['partial_start', 'partial_end', 'contained', 'containing']);

            switch ($overlapType) {
                case 'partial_start':
                    // New starts before existing ends, new ends after existing starts
                    $overlapMinutes = $this->faker->numberBetween(1, $existingDuration - 1);
                    $newStart = $existingStart->copy()->addMinutes($overlapMinutes);
                    $newEnd = $existingEnd->copy()->addMinutes($this->faker->numberBetween(1, 60));
                    break;

                case 'partial_end':
                    // New starts before existing, new ends within existing
                    $overlapMinutes = $this->faker->numberBetween(1, $existingDuration - 1);
                    $newStart = $existingStart->copy()->subMinutes($this->faker->numberBetween(1, 60));
                    $newEnd = $existingStart->copy()->addMinutes($overlapMinutes);
                    break;

                case 'contained':
                    // New is fully inside existing
                    $margin = max(1, intdiv($existingDuration, 4));
                    $newStart = $existingStart->copy()->addMinutes($this->faker->numberBetween(1, $margin));
                    $newEnd = $existingEnd->copy()->subMinutes($this->faker->numberBetween(1, $margin));
                    if ($newEnd->lte($newStart)) {
                        $newEnd = $newStart->copy()->addMinutes(1);
                    }
                    break;

                case 'containing':
                    // New fully contains existing
                    $newStart = $existingStart->copy()->subMinutes($this->faker->numberBetween(1, 60));
                    $newEnd = $existingEnd->copy()->addMinutes($this->faker->numberBetween(1, 60));
                    break;
            }

            // Verify our test data actually overlaps per the formal definition
            $this->assertTrue(
                $this->periodsOverlap($existingStart, $existingEnd, $newStart, $newEnd),
                "Iteration {$i}: Test data generation error - periods should overlap. "
                . "Type: {$overlapType}, Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertFalse(
                $result,
                "Iteration {$i}: Overlapping ({$overlapType}) should be rejected. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Cancelled and no_show appointments should never block new appointments.
     */
    public function test_property_inactive_statuses_never_block(): void
    {
        $baseDate = Carbon::parse('2025-06-15 10:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(30, 180);
            $existingStart = $baseDate->copy();
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            // Use only inactive statuses
            $inactiveStatus = $this->faker->randomElement([
                AppointmentStatus::Cancelled,
                AppointmentStatus::NoShow,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $inactiveStatus);

            // New appointment that would overlap if existing were active
            $newStart = $existingStart->copy()->addMinutes($this->faker->numberBetween(1, $existingDuration - 1));
            $newEnd = $existingEnd->copy()->addMinutes($this->faker->numberBetween(1, 60));

            $result = $this->rulePassesFor($newStart, $newEnd);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Inactive status ({$inactiveStatus->value}) should never block. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: Self-exclusion on update - updating an appointment should not
     * conflict with itself.
     */
    public function test_property_self_exclusion_on_update(): void
    {
        $baseDate = Carbon::parse('2025-06-15 10:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            $existingDuration = $this->faker->numberBetween(30, 180);
            $existingStart = $baseDate->copy()->addMinutes($this->faker->numberBetween(0, 480));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $appointment = $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // Update to a new time that overlaps with original (shift by random amount)
            $shift = $this->faker->numberBetween(-60, 60);
            $newStart = $existingStart->copy()->addMinutes($shift);
            $newDuration = $this->faker->numberBetween(15, 240);
            $newEnd = $newStart->copy()->addMinutes($newDuration);

            // With self-exclusion, should always pass (no other appointments exist)
            $result = $this->rulePassesFor($newStart, $newEnd, $appointment->id);

            $this->assertTrue(
                $result,
                "Iteration {$i}: Self-exclusion should always pass when no other appointments. "
                . "Original: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}]"
            );
        }
    }

    /**
     * Property test: The overlap detection is symmetric with respect to the formal
     * definition - for random time periods, the rule accepts iff no overlap exists.
     */
    public function test_property_overlap_detection_matches_formal_definition(): void
    {
        $baseDate = Carbon::parse('2025-06-15 08:00:00', 'UTC');

        for ($i = 0; $i < 100; $i++) {
            Appointment::query()->delete();

            // Create an existing appointment
            $existingDuration = $this->faker->numberBetween(15, 180);
            $existingStart = $baseDate->copy()->addMinutes($this->faker->numberBetween(0, 600));
            $existingEnd = $existingStart->copy()->addMinutes($existingDuration);

            $activeStatus = $this->faker->randomElement([
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
                AppointmentStatus::InProgress,
                AppointmentStatus::Completed,
            ]);

            $this->createAppointment($existingStart, $existingEnd, $activeStatus);

            // Generate a random new time period
            $newStart = $baseDate->copy()->addMinutes($this->faker->numberBetween(0, 600));
            $newDuration = $this->faker->numberBetween(15, 180);
            $newEnd = $newStart->copy()->addMinutes($newDuration);

            // Compute expected result using formal overlap definition
            $shouldOverlap = $this->periodsOverlap($existingStart, $existingEnd, $newStart, $newEnd);

            $rulePasses = $this->rulePassesFor($newStart, $newEnd);

            // Rule should reject (fail) iff overlap exists
            $this->assertEquals(
                !$shouldOverlap,
                $rulePasses,
                "Iteration {$i}: Rule result should match formal definition. "
                . "Existing: [{$existingStart}..{$existingEnd}], New: [{$newStart}..{$newEnd}], "
                . "Expected overlap: " . ($shouldOverlap ? 'yes' : 'no')
                . ", Rule passed: " . ($rulePasses ? 'yes' : 'no')
            );
        }
    }
}
