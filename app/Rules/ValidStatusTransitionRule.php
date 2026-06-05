<?php

namespace App\Rules;

use App\Enums\AppointmentStatus;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidStatusTransitionRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param AppointmentStatus $currentStatus The current status of the appointment.
     */
    public function __construct(
        protected AppointmentStatus $currentStatus
    ) {}

    /**
     * Run the validation rule.
     *
     * Validates that the transition from the current status to the target status
     * is in the allowed set of transitions.
     *
     * @param string $attribute
     * @param mixed $value The target status string value.
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $targetStatus = AppointmentStatus::tryFrom($value);

        if ($targetStatus === null) {
            $fail("The selected status is invalid.");
            return;
        }

        if (! $this->currentStatus->canTransitionTo($targetStatus)) {
            $fail("Cannot transition from {$this->currentStatus->value} to {$targetStatus->value}.");
        }
    }
}
