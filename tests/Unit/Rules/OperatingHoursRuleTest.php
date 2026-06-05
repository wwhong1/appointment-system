<?php

namespace Tests\Unit\Rules;

use App\Models\Branch;
use App\Rules\OperatingHoursRule;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class OperatingHoursRuleTest extends TestCase
{
    private function makeBranch(string $timezone, string $opening, string $closing): Branch
    {
        $branch = new Branch();
        $branch->timezone = $timezone;
        $branch->opening_time = $opening;
        $branch->closing_time = $closing;

        return $branch;
    }

    private function runRule(OperatingHoursRule $rule): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('start_datetime', null, $fail);

        return $errors;
    }

    public function test_passes_when_appointment_is_within_operating_hours(): void
    {
        // Branch in Asia/Kuala_Lumpur (UTC+8), open 09:00-17:00
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 10:00 KL time = 02:00 UTC, 11:00 KL time = 03:00 UTC
        $startUtc = Carbon::parse('2025-01-15 02:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 03:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_appointment_starts_exactly_at_opening(): void
    {
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 09:00 KL time = 01:00 UTC
        $startUtc = Carbon::parse('2025-01-15 01:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 02:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_appointment_ends_exactly_at_closing(): void
    {
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 16:00 KL = 08:00 UTC, 17:00 KL = 09:00 UTC
        $startUtc = Carbon::parse('2025-01-15 08:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 09:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_fails_when_start_is_before_opening_time(): void
    {
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 08:30 KL time = 00:30 UTC (before 09:00 opening)
        $startUtc = Carbon::parse('2025-01-15 00:30:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 02:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('start time is before branch opening hours', $errors[0]);
        $this->assertStringContainsString('09:00:00', $errors[0]);
        $this->assertStringContainsString('Asia/Kuala_Lumpur', $errors[0]);
    }

    public function test_fails_when_end_is_after_closing_time(): void
    {
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 16:00 KL = 08:00 UTC, 17:30 KL = 09:30 UTC (after 17:00 closing)
        $startUtc = Carbon::parse('2025-01-15 08:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 09:30:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('end time is after branch closing hours', $errors[0]);
        $this->assertStringContainsString('17:00:00', $errors[0]);
        $this->assertStringContainsString('Asia/Kuala_Lumpur', $errors[0]);
    }

    public function test_fails_with_both_errors_when_both_outside_hours(): void
    {
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 08:00 KL = 00:00 UTC (before opening), 18:00 KL = 10:00 UTC (after closing)
        $startUtc = Carbon::parse('2025-01-15 00:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 10:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertCount(2, $errors);
        $this->assertStringContainsString('start time is before branch opening hours', $errors[0]);
        $this->assertStringContainsString('end time is after branch closing hours', $errors[1]);
    }

    public function test_handles_different_timezone_correctly(): void
    {
        // Branch in America/New_York (UTC-5 in winter)
        $branch = $this->makeBranch('America/New_York', '08:00:00', '18:00:00');

        // 10:00 NY = 15:00 UTC (within hours)
        $startUtc = Carbon::parse('2025-01-15 15:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 16:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_handles_utc_timezone_branch(): void
    {
        $branch = $this->makeBranch('UTC', '09:00:00', '17:00:00');

        // Already in UTC, 10:00-11:00 within hours
        $startUtc = Carbon::parse('2025-01-15 10:00:00', 'UTC');
        $endUtc = Carbon::parse('2025-01-15 11:00:00', 'UTC');

        $rule = new OperatingHoursRule($branch, $startUtc, $endUtc);
        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_validates_time_portion_only_not_date(): void
    {
        // The rule validates time portion only - same time on different dates should behave the same
        $branch = $this->makeBranch('Asia/Kuala_Lumpur', '09:00:00', '17:00:00');

        // 10:00 KL on a Monday
        $startUtc1 = Carbon::parse('2025-01-13 02:00:00', 'UTC');
        $endUtc1 = Carbon::parse('2025-01-13 03:00:00', 'UTC');

        // 10:00 KL on a Saturday
        $startUtc2 = Carbon::parse('2025-01-18 02:00:00', 'UTC');
        $endUtc2 = Carbon::parse('2025-01-18 03:00:00', 'UTC');

        $rule1 = new OperatingHoursRule($branch, $startUtc1, $endUtc1);
        $rule2 = new OperatingHoursRule($branch, $startUtc2, $endUtc2);

        $this->assertEmpty($this->runRule($rule1));
        $this->assertEmpty($this->runRule($rule2));
    }
}
