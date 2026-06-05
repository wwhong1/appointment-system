<?php

namespace Tests\Unit\Rules;

use App\Enums\AppointmentStatus;
use App\Rules\ValidStatusTransitionRule;
use PHPUnit\Framework\TestCase;

class ValidStatusTransitionRuleTest extends TestCase
{
    /**
     * Test that valid transitions pass validation.
     */
    public function test_valid_transitions_pass(): void
    {
        $validTransitions = [
            [AppointmentStatus::Pending, 'confirmed'],
            [AppointmentStatus::Pending, 'cancelled'],
            [AppointmentStatus::Confirmed, 'in-progress'],
            [AppointmentStatus::Confirmed, 'cancelled'],
            [AppointmentStatus::Confirmed, 'no_show'],
            [AppointmentStatus::InProgress, 'completed'],
        ];

        foreach ($validTransitions as [$currentStatus, $targetValue]) {
            $rule = new ValidStatusTransitionRule($currentStatus);
            $failed = false;

            $rule->validate('status', $targetValue, function () use (&$failed) {
                $failed = true;
            });

            $this->assertFalse(
                $failed,
                "Transition from {$currentStatus->value} to {$targetValue} should be valid"
            );
        }
    }

    /**
     * Test that invalid transitions fail validation.
     */
    public function test_invalid_transitions_fail(): void
    {
        $invalidTransitions = [
            [AppointmentStatus::Pending, 'in-progress'],
            [AppointmentStatus::Pending, 'completed'],
            [AppointmentStatus::Pending, 'no_show'],
            [AppointmentStatus::Confirmed, 'pending'],
            [AppointmentStatus::Confirmed, 'completed'],
            [AppointmentStatus::InProgress, 'pending'],
            [AppointmentStatus::InProgress, 'confirmed'],
            [AppointmentStatus::InProgress, 'cancelled'],
            [AppointmentStatus::InProgress, 'no_show'],
            [AppointmentStatus::Completed, 'pending'],
            [AppointmentStatus::Completed, 'confirmed'],
            [AppointmentStatus::Cancelled, 'pending'],
            [AppointmentStatus::Cancelled, 'confirmed'],
            [AppointmentStatus::NoShow, 'pending'],
            [AppointmentStatus::NoShow, 'confirmed'],
        ];

        foreach ($invalidTransitions as [$currentStatus, $targetValue]) {
            $rule = new ValidStatusTransitionRule($currentStatus);
            $failed = false;

            $rule->validate('status', $targetValue, function () use (&$failed) {
                $failed = true;
            });

            $this->assertTrue(
                $failed,
                "Transition from {$currentStatus->value} to {$targetValue} should be invalid"
            );
        }
    }

    /**
     * Test that terminal statuses reject all transitions.
     */
    public function test_terminal_statuses_reject_all_transitions(): void
    {
        $terminalStatuses = [
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ];

        foreach ($terminalStatuses as $currentStatus) {
            foreach (AppointmentStatus::cases() as $target) {
                $rule = new ValidStatusTransitionRule($currentStatus);
                $failed = false;

                $rule->validate('status', $target->value, function () use (&$failed) {
                    $failed = true;
                });

                $this->assertTrue(
                    $failed,
                    "Terminal status {$currentStatus->value} should reject transition to {$target->value}"
                );
            }
        }
    }

    /**
     * Test that an invalid status string fails with appropriate message.
     */
    public function test_invalid_status_string_fails(): void
    {
        $rule = new ValidStatusTransitionRule(AppointmentStatus::Pending);
        $failMessage = null;

        $rule->validate('status', 'nonexistent_status', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertSame('The selected status is invalid.', $failMessage);
    }

    /**
     * Test that the error message includes current and target status values.
     */
    public function test_error_message_includes_status_values(): void
    {
        $rule = new ValidStatusTransitionRule(AppointmentStatus::Pending);
        $failMessage = null;

        $rule->validate('status', 'completed', function ($message) use (&$failMessage) {
            $failMessage = $message;
        });

        $this->assertSame('Cannot transition from pending to completed.', $failMessage);
    }
}
