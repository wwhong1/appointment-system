<?php

namespace Tests\Feature\Properties;

use App\Enums\AppointmentStatus;
use App\Rules\ValidStatusTransitionRule;
use Faker\Factory as Faker;
use PHPUnit\Framework\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 12: Valid status transitions (state machine)
 *
 * Validates: Requirements 9.1, 9.2, 9.5
 *
 * For any appointment with current status S and attempted target status T,
 * the system SHALL allow the transition if and only if (S, T) is in the set
 * {(pending, confirmed), (pending, cancelled), (confirmed, in-progress),
 * (confirmed, cancelled), (confirmed, no_show), (in-progress, completed)}.
 * All other transitions SHALL be rejected.
 * Statuses completed, cancelled, and no_show SHALL have no valid outgoing transitions.
 */
class StatusTransitionTest extends TestCase
{
    /**
     * The complete set of valid transitions as defined by the state machine.
     */
    private const VALID_TRANSITIONS = [
        ['pending', 'confirmed'],
        ['pending', 'cancelled'],
        ['confirmed', 'in-progress'],
        ['confirmed', 'cancelled'],
        ['confirmed', 'no_show'],
        ['in-progress', 'completed'],
    ];

    /**
     * All possible statuses.
     */
    private const ALL_STATUSES = [
        'pending',
        'confirmed',
        'in-progress',
        'completed',
        'cancelled',
        'no_show',
    ];

    /**
     * Terminal statuses with no valid outgoing transitions.
     */
    private const TERMINAL_STATUSES = [
        'completed',
        'cancelled',
        'no_show',
    ];

    /**
     * Helper to check if a (current, target) pair is a valid transition.
     */
    private function isValidTransition(string $current, string $target): bool
    {
        foreach (self::VALID_TRANSITIONS as [$from, $to]) {
            if ($from === $current && $to === $target) {
                return true;
            }
        }
        return false;
    }

    /**
     * Helper to run the ValidStatusTransitionRule and return whether it passed.
     */
    private function rulePassesTransition(string $currentStatusValue, string $targetStatusValue): bool
    {
        $currentStatus = AppointmentStatus::from($currentStatusValue);
        $rule = new ValidStatusTransitionRule($currentStatus);
        $failed = false;

        $rule->validate('status', $targetStatusValue, function () use (&$failed) {
            $failed = true;
        });

        return !$failed;
    }

    /**
     * Property 12: Exhaustive test of all 36 possible (S, T) combinations.
     *
     * For every combination of current status and target status (6×6 = 36 pairs),
     * the ValidStatusTransitionRule SHALL accept the transition if and only if
     * the pair is in the valid transitions set.
     *
     * **Validates: Requirements 9.1, 9.2, 9.5**
     */
    public function test_property_exhaustive_all_36_status_pairs(): void
    {
        foreach (self::ALL_STATUSES as $current) {
            foreach (self::ALL_STATUSES as $target) {
                $passes = $this->rulePassesTransition($current, $target);
                $expectedValid = $this->isValidTransition($current, $target);

                $this->assertSame(
                    $expectedValid,
                    $passes,
                    "Transition from '{$current}' to '{$target}' should be "
                    . ($expectedValid ? 'ACCEPTED' : 'REJECTED')
                    . ' but was ' . ($passes ? 'ACCEPTED' : 'REJECTED')
                );
            }
        }
    }

    /**
     * Property 12: Random sampling of status pairs over 100+ iterations.
     *
     * For any randomly selected (current, target) status pair, the
     * ValidStatusTransitionRule SHALL accept the transition if and only if
     * the pair is in the valid transitions set.
     *
     * **Validates: Requirements 9.1, 9.2, 9.5**
     */
    public function test_property_random_status_pairs_100_iterations(): void
    {
        $faker = Faker::create();
        $iterations = 150;

        for ($i = 0; $i < $iterations; $i++) {
            $current = $faker->randomElement(self::ALL_STATUSES);
            $target = $faker->randomElement(self::ALL_STATUSES);

            $passes = $this->rulePassesTransition($current, $target);
            $expectedValid = $this->isValidTransition($current, $target);

            $this->assertSame(
                $expectedValid,
                $passes,
                "Iteration {$i}: Transition from '{$current}' to '{$target}' should be "
                . ($expectedValid ? 'ACCEPTED' : 'REJECTED')
                . ' but was ' . ($passes ? 'ACCEPTED' : 'REJECTED')
            );
        }
    }

    /**
     * Property 12: Terminal statuses have no valid outgoing transitions.
     *
     * For any terminal status (completed, cancelled, no_show) and any target status,
     * the ValidStatusTransitionRule SHALL reject the transition.
     *
     * **Validates: Requirements 9.5**
     */
    public function test_property_terminal_statuses_reject_all_transitions(): void
    {
        $faker = Faker::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $terminalStatus = $faker->randomElement(self::TERMINAL_STATUSES);
            $target = $faker->randomElement(self::ALL_STATUSES);

            $passes = $this->rulePassesTransition($terminalStatus, $target);

            $this->assertFalse(
                $passes,
                "Iteration {$i}: Terminal status '{$terminalStatus}' should reject "
                . "transition to '{$target}' but it was accepted"
            );
        }
    }

    /**
     * Property 12: Valid transitions are always accepted.
     *
     * For any randomly selected valid transition pair from the allowed set,
     * the ValidStatusTransitionRule SHALL accept the transition.
     *
     * **Validates: Requirements 9.1**
     */
    public function test_property_valid_transitions_always_accepted(): void
    {
        $faker = Faker::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $transition = $faker->randomElement(self::VALID_TRANSITIONS);
            [$current, $target] = $transition;

            $passes = $this->rulePassesTransition($current, $target);

            $this->assertTrue(
                $passes,
                "Iteration {$i}: Valid transition from '{$current}' to '{$target}' "
                . "should be accepted but was rejected"
            );
        }
    }

    /**
     * Property 12: Self-transitions are always rejected.
     *
     * For any status S, the transition (S, S) SHALL be rejected since
     * no self-transitions are in the valid set.
     *
     * **Validates: Requirements 9.2**
     */
    public function test_property_self_transitions_always_rejected(): void
    {
        $faker = Faker::create();
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $status = $faker->randomElement(self::ALL_STATUSES);

            $passes = $this->rulePassesTransition($status, $status);

            $this->assertFalse(
                $passes,
                "Iteration {$i}: Self-transition from '{$status}' to '{$status}' "
                . "should be rejected but was accepted"
            );
        }
    }

    /**
     * Property 12: Invalid status strings are always rejected.
     *
     * For any current status and an invalid target status string,
     * the ValidStatusTransitionRule SHALL reject the transition.
     *
     * **Validates: Requirements 9.2**
     */
    public function test_property_invalid_status_strings_rejected(): void
    {
        $faker = Faker::create();
        $iterations = 100;

        $invalidStatuses = [
            'active', 'done', 'waiting', 'scheduled', 'approved',
            'rejected', 'expired', 'paused', 'archived', 'deleted',
            '', 'PENDING', 'Confirmed', 'IN_PROGRESS', 'complete',
        ];

        for ($i = 0; $i < $iterations; $i++) {
            $current = $faker->randomElement(self::ALL_STATUSES);
            $invalidTarget = $faker->randomElement($invalidStatuses);

            $currentStatus = AppointmentStatus::from($current);
            $rule = new ValidStatusTransitionRule($currentStatus);
            $failed = false;

            $rule->validate('status', $invalidTarget, function () use (&$failed) {
                $failed = true;
            });

            $this->assertTrue(
                $failed,
                "Iteration {$i}: Invalid status string '{$invalidTarget}' from '{$current}' "
                . "should be rejected but was accepted"
            );
        }
    }
}
