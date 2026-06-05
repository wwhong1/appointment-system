<?php

namespace App\Enums;

enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case InProgress = 'in-progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    /**
     * Get the list of statuses this status can transition to.
     *
     * Valid transitions (Requirement 9.1):
     * - pending → confirmed, cancelled
     * - confirmed → in-progress, cancelled, no_show
     * - in-progress → completed
     * - completed, cancelled, no_show → (none, terminal)
     *
     * @return array<self>
     */
    public function validTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::InProgress, self::Cancelled, self::NoShow],
            self::InProgress => [self::Completed],
            self::Completed, self::Cancelled, self::NoShow => [],
        };
    }

    /**
     * Check if this status can transition to the given target status.
     *
     * @param self $target The target status to transition to.
     * @return bool True if the transition is valid.
     */
    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->validTransitions(), true);
    }

    /**
     * Check if this status is terminal (no valid outgoing transitions).
     *
     * Terminal statuses (Requirement 9.5): completed, cancelled, no_show
     *
     * @return bool True if this is a terminal status.
     */
    public function isTerminal(): bool
    {
        return match ($this) {
            self::Completed, self::Cancelled, self::NoShow => true,
            default => false,
        };
    }

    /**
     * Check if this status is considered active (blocks staff availability).
     *
     * Active statuses are used for overlap blocking (Requirement 8.3):
     * pending, confirmed, in-progress, completed
     *
     * @return bool True if this is an active status.
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::Pending, self::Confirmed, self::InProgress, self::Completed => true,
            self::Cancelled, self::NoShow => false,
        };
    }
}
