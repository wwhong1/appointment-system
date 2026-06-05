<?php

namespace App\Rules;

use App\Models\Branch;
use Carbon\Carbon;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class OperatingHoursRule implements ValidationRule
{
    public function __construct(
        protected Branch $branch,
        protected Carbon $startUtc,
        protected Carbon $endUtc,
    ) {}

    /**
     * Run the validation rule.
     *
     * Converts start/end UTC datetimes to the branch's local timezone
     * and validates the time portion falls within operating hours.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $timezone = $this->branch->timezone;
        $startLocal = $this->startUtc->copy()->setTimezone($timezone);
        $endLocal = $this->endUtc->copy()->setTimezone($timezone);

        $openingTime = Carbon::parse($this->branch->opening_time)->format('H:i:s');
        $closingTime = Carbon::parse($this->branch->closing_time)->format('H:i:s');

        $startTime = $startLocal->format('H:i:s');
        $endTime = $endLocal->format('H:i:s');

        if ($startTime < $openingTime) {
            $fail("Appointment start time is before branch opening hours ({$this->branch->opening_time} {$timezone}).");
        }

        if ($endTime > $closingTime) {
            $fail("Appointment end time is after branch closing hours ({$this->branch->closing_time} {$timezone}).");
        }
    }
}
