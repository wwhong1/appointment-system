<?php

namespace Tests\Unit\Enums;

use App\Enums\AppointmentStatus;
use PHPUnit\Framework\TestCase;

class AppointmentStatusTest extends TestCase
{
    /**
     * Test that all enum cases have the correct string values.
     */
    public function test_enum_cases_have_correct_values(): void
    {
        $this->assertSame('pending', AppointmentStatus::Pending->value);
        $this->assertSame('confirmed', AppointmentStatus::Confirmed->value);
        $this->assertSame('in-progress', AppointmentStatus::InProgress->value);
        $this->assertSame('completed', AppointmentStatus::Completed->value);
        $this->assertSame('cancelled', AppointmentStatus::Cancelled->value);
        $this->assertSame('no_show', AppointmentStatus::NoShow->value);
    }

    /**
     * Test valid transitions from pending status.
     */
    public function test_pending_can_transition_to_confirmed_and_cancelled(): void
    {
        $this->assertTrue(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Confirmed));
        $this->assertTrue(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Cancelled));
    }

    /**
     * Test invalid transitions from pending status.
     */
    public function test_pending_cannot_transition_to_other_statuses(): void
    {
        $this->assertFalse(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Pending));
        $this->assertFalse(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::InProgress));
        $this->assertFalse(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::Completed));
        $this->assertFalse(AppointmentStatus::Pending->canTransitionTo(AppointmentStatus::NoShow));
    }

    /**
     * Test valid transitions from confirmed status.
     */
    public function test_confirmed_can_transition_to_in_progress_cancelled_and_no_show(): void
    {
        $this->assertTrue(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::InProgress));
        $this->assertTrue(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Cancelled));
        $this->assertTrue(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::NoShow));
    }

    /**
     * Test invalid transitions from confirmed status.
     */
    public function test_confirmed_cannot_transition_to_other_statuses(): void
    {
        $this->assertFalse(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Pending));
        $this->assertFalse(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Confirmed));
        $this->assertFalse(AppointmentStatus::Confirmed->canTransitionTo(AppointmentStatus::Completed));
    }

    /**
     * Test valid transitions from in-progress status.
     */
    public function test_in_progress_can_transition_to_completed(): void
    {
        $this->assertTrue(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::Completed));
    }

    /**
     * Test invalid transitions from in-progress status.
     */
    public function test_in_progress_cannot_transition_to_other_statuses(): void
    {
        $this->assertFalse(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::Pending));
        $this->assertFalse(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::Confirmed));
        $this->assertFalse(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::InProgress));
        $this->assertFalse(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::Cancelled));
        $this->assertFalse(AppointmentStatus::InProgress->canTransitionTo(AppointmentStatus::NoShow));
    }

    /**
     * Test terminal statuses have no valid transitions.
     */
    public function test_terminal_statuses_have_no_valid_transitions(): void
    {
        $terminalStatuses = [
            AppointmentStatus::Completed,
            AppointmentStatus::Cancelled,
            AppointmentStatus::NoShow,
        ];

        foreach ($terminalStatuses as $status) {
            $this->assertEmpty(
                $status->validTransitions(),
                "Terminal status {$status->value} should have no valid transitions"
            );

            foreach (AppointmentStatus::cases() as $target) {
                $this->assertFalse(
                    $status->canTransitionTo($target),
                    "Terminal status {$status->value} should not transition to {$target->value}"
                );
            }
        }
    }

    /**
     * Test isTerminal returns true for terminal statuses.
     */
    public function test_is_terminal_returns_true_for_terminal_statuses(): void
    {
        $this->assertTrue(AppointmentStatus::Completed->isTerminal());
        $this->assertTrue(AppointmentStatus::Cancelled->isTerminal());
        $this->assertTrue(AppointmentStatus::NoShow->isTerminal());
    }

    /**
     * Test isTerminal returns false for non-terminal statuses.
     */
    public function test_is_terminal_returns_false_for_non_terminal_statuses(): void
    {
        $this->assertFalse(AppointmentStatus::Pending->isTerminal());
        $this->assertFalse(AppointmentStatus::Confirmed->isTerminal());
        $this->assertFalse(AppointmentStatus::InProgress->isTerminal());
    }

    /**
     * Test isActive returns true for active statuses (blocking availability).
     */
    public function test_is_active_returns_true_for_active_statuses(): void
    {
        $this->assertTrue(AppointmentStatus::Pending->isActive());
        $this->assertTrue(AppointmentStatus::Confirmed->isActive());
        $this->assertTrue(AppointmentStatus::InProgress->isActive());
        $this->assertTrue(AppointmentStatus::Completed->isActive());
    }

    /**
     * Test isActive returns false for inactive statuses (not blocking availability).
     */
    public function test_is_active_returns_false_for_inactive_statuses(): void
    {
        $this->assertFalse(AppointmentStatus::Cancelled->isActive());
        $this->assertFalse(AppointmentStatus::NoShow->isActive());
    }

    /**
     * Test validTransitions returns correct arrays for each status.
     */
    public function test_valid_transitions_returns_correct_arrays(): void
    {
        $this->assertEquals(
            [AppointmentStatus::Confirmed, AppointmentStatus::Cancelled],
            AppointmentStatus::Pending->validTransitions()
        );

        $this->assertEquals(
            [AppointmentStatus::InProgress, AppointmentStatus::Cancelled, AppointmentStatus::NoShow],
            AppointmentStatus::Confirmed->validTransitions()
        );

        $this->assertEquals(
            [AppointmentStatus::Completed],
            AppointmentStatus::InProgress->validTransitions()
        );

        $this->assertEquals([], AppointmentStatus::Completed->validTransitions());
        $this->assertEquals([], AppointmentStatus::Cancelled->validTransitions());
        $this->assertEquals([], AppointmentStatus::NoShow->validTransitions());
    }
}
