<?php

namespace App\Rules;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class NoOverlapRule implements ValidationRule
{
    /**
     * Create a new rule instance.
     *
     * @param int $staffId The staff member's ID to check for overlaps.
     * @param Carbon $startDatetime The appointment start datetime in UTC.
     * @param Carbon $endDatetime The appointment end datetime in UTC.
     * @param int|null $excludeAppointmentId The appointment ID to exclude (for updates).
     */
    public function __construct(
        protected int $staffId,
        protected Carbon $startDatetime,
        protected Carbon $endDatetime,
        protected ?int $excludeAppointmentId = null,
    ) {}

    /**
     * Run the validation rule.
     *
     * Validates that the staff member has no active appointments whose time period
     * intersects with the requested appointment time period.
     *
     * Intersection logic: existing.start < new.end AND existing.end > new.start
     * Adjacent appointments (existing.end == new.start) are NOT overlapping.
     *
     * Uses lockForUpdate() for race condition prevention.
     *
     * @param string $attribute
     * @param mixed $value
     * @param Closure $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $activeStatuses = collect(AppointmentStatus::cases())
            ->filter(fn (AppointmentStatus $status) => $status->isActive())
            ->map(fn (AppointmentStatus $status) => $status->value)
            ->values()
            ->all();

        $query = Appointment::query()
            ->where('staff_id', $this->staffId)
            ->whereIn('status', $activeStatuses)
            ->where('start_datetime', '<', $this->endDatetime)
            ->where('end_datetime', '>', $this->startDatetime)
            ->lockForUpdate();

        if ($this->excludeAppointmentId !== null) {
            $query->where('id', '!=', $this->excludeAppointmentId);
        }

        $conflicting = $query->first();

        if ($conflicting !== null) {
            $staffName = $conflicting->staff->name ?? 'Unknown';
            $start = $conflicting->start_datetime->format('Y-m-d H:i');
            $end = $conflicting->end_datetime->format('Y-m-d H:i');

            $fail("Staff member {$staffName} has a conflicting appointment from {$start} to {$end}.");
        }
    }
}
