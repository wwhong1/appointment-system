<?php

namespace Tests\Feature\Properties;

use App\Models\Branch;
use App\Rules\OperatingHoursRule;
use Carbon\Carbon;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 9: Operating hours validation (timezone-aware)
 *
 * For any appointment with start and end datetimes (UTC), and a branch with
 * timezone T, opening time O, and closing time C, the system SHALL accept the
 * appointment if and only if the start time converted to timezone T is at or
 * after O AND the end time converted to timezone T is at or before C.
 *
 * Validates: Requirements 6.1, 6.2, 6.3
 */
class OperatingHoursValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A representative subset of IANA timezones for testing.
     */
    private static array $timezones = [
        'UTC',
        'America/New_York',
        'America/Los_Angeles',
        'America/Chicago',
        'Europe/London',
        'Europe/Paris',
        'Europe/Berlin',
        'Asia/Tokyo',
        'Asia/Shanghai',
        'Asia/Kuala_Lumpur',
        'Asia/Kolkata',
        'Australia/Sydney',
        'Australia/Perth',
        'Pacific/Auckland',
        'Africa/Cairo',
        'America/Sao_Paulo',
    ];

    private function runRule(OperatingHoursRule $rule): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('start_datetime', null, $fail);

        return $errors;
    }

    /**
     * Generate test cases where the appointment is within operating hours.
     * The appointment start (local) is at or after opening, and end (local) is at or before closing.
     */
    public static function validAppointmentsProvider(): array
    {
        $faker = Faker::create();
        $cases = [];

        for ($i = 0; $i < 100; $i++) {
            $timezone = $faker->randomElement(self::$timezones);

            // Generate opening and closing times ensuring opening < closing with enough gap
            $openingHour = $faker->numberBetween(6, 12);
            $openingMinute = $faker->randomElement([0, 15, 30, 45]);
            $openingTime = sprintf('%02d:%02d:00', $openingHour, $openingMinute);

            // Closing must be at least 2 hours after opening
            $closingHour = $faker->numberBetween($openingHour + 2, 22);
            $closingMinute = $faker->randomElement([0, 15, 30, 45]);
            $closingTime = sprintf('%02d:%02d:00', $closingHour, $closingMinute);

            // Generate appointment start local time that is at or after opening
            $availableStartMinutes = ($closingHour * 60 + $closingMinute) - ($openingHour * 60 + $openingMinute);
            // Leave at least 15 minutes for the appointment duration
            $maxStartOffset = max(0, $availableStartMinutes - 15);
            $startOffsetMinutes = $faker->numberBetween(0, $maxStartOffset);

            $startTotalMinutes = ($openingHour * 60 + $openingMinute) + $startOffsetMinutes;
            $startLocalHour = intdiv($startTotalMinutes, 60);
            $startLocalMinute = $startTotalMinutes % 60;

            // Generate appointment end local time that is at or before closing
            $remainingMinutes = ($closingHour * 60 + $closingMinute) - $startTotalMinutes;
            $duration = $faker->numberBetween(1, max(1, $remainingMinutes));

            $endTotalMinutes = $startTotalMinutes + $duration;
            $endLocalHour = intdiv($endTotalMinutes, 60);
            $endLocalMinute = $endTotalMinutes % 60;

            // Create local datetimes and convert to UTC
            $date = $faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');
            $startLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $startLocalHour, $startLocalMinute),
                $timezone
            );
            $endLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $endLocalHour, $endLocalMinute),
                $timezone
            );

            $startUtc = $startLocal->copy()->setTimezone('UTC');
            $endUtc = $endLocal->copy()->setTimezone('UTC');

            $cases["valid_case_{$i}_tz_{$timezone}"] = [
                $timezone,
                $openingTime,
                $closingTime,
                $startUtc->toDateTimeString(),
                $endUtc->toDateTimeString(),
                true,
            ];
        }

        return $cases;
    }

    /**
     * Generate test cases where the appointment start is before opening hours.
     */
    public static function invalidStartBeforeOpeningProvider(): array
    {
        $faker = Faker::create();
        $cases = [];

        for ($i = 0; $i < 50; $i++) {
            $timezone = $faker->randomElement(self::$timezones);

            // Generate opening time (not too early so we have room before it)
            $openingHour = $faker->numberBetween(7, 14);
            $openingMinute = $faker->randomElement([0, 15, 30, 45]);
            $openingTime = sprintf('%02d:%02d:00', $openingHour, $openingMinute);

            $closingHour = $faker->numberBetween($openingHour + 2, 22);
            $closingMinute = $faker->randomElement([0, 15, 30, 45]);
            $closingTime = sprintf('%02d:%02d:00', $closingHour, $closingMinute);

            // Generate start time BEFORE opening (1 to 60 minutes before)
            $openingTotalMinutes = $openingHour * 60 + $openingMinute;
            $minutesBefore = $faker->numberBetween(1, min(60, $openingTotalMinutes));
            $startTotalMinutes = $openingTotalMinutes - $minutesBefore;
            $startLocalHour = intdiv($startTotalMinutes, 60);
            $startLocalMinute = $startTotalMinutes % 60;

            // End time within operating hours (at or before closing)
            $closingTotalMinutes = $closingHour * 60 + $closingMinute;
            $endTotalMinutes = $faker->numberBetween($openingTotalMinutes, $closingTotalMinutes);
            $endLocalHour = intdiv($endTotalMinutes, 60);
            $endLocalMinute = $endTotalMinutes % 60;

            $date = $faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');
            $startLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $startLocalHour, $startLocalMinute),
                $timezone
            );
            $endLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $endLocalHour, $endLocalMinute),
                $timezone
            );

            $startUtc = $startLocal->copy()->setTimezone('UTC');
            $endUtc = $endLocal->copy()->setTimezone('UTC');

            $cases["start_before_opening_{$i}_tz_{$timezone}"] = [
                $timezone,
                $openingTime,
                $closingTime,
                $startUtc->toDateTimeString(),
                $endUtc->toDateTimeString(),
                false,
            ];
        }

        return $cases;
    }

    /**
     * Generate test cases where the appointment end is after closing hours.
     */
    public static function invalidEndAfterClosingProvider(): array
    {
        $faker = Faker::create();
        $cases = [];

        for ($i = 0; $i < 50; $i++) {
            $timezone = $faker->randomElement(self::$timezones);

            $openingHour = $faker->numberBetween(6, 12);
            $openingMinute = $faker->randomElement([0, 15, 30, 45]);
            $openingTime = sprintf('%02d:%02d:00', $openingHour, $openingMinute);

            // Closing time not too late so we have room after it
            $closingHour = $faker->numberBetween($openingHour + 2, 21);
            $closingMinute = $faker->randomElement([0, 15, 30, 45]);
            $closingTime = sprintf('%02d:%02d:00', $closingHour, $closingMinute);

            // Start time within operating hours
            $openingTotalMinutes = $openingHour * 60 + $openingMinute;
            $closingTotalMinutes = $closingHour * 60 + $closingMinute;
            $startTotalMinutes = $faker->numberBetween($openingTotalMinutes, $closingTotalMinutes - 1);
            $startLocalHour = intdiv($startTotalMinutes, 60);
            $startLocalMinute = $startTotalMinutes % 60;

            // End time AFTER closing (1 to 60 minutes after)
            $maxMinutesAfter = min(60, (23 * 60 + 59) - $closingTotalMinutes);
            $minutesAfter = $faker->numberBetween(1, max(1, $maxMinutesAfter));
            $endTotalMinutes = $closingTotalMinutes + $minutesAfter;
            $endLocalHour = intdiv($endTotalMinutes, 60);
            $endLocalMinute = $endTotalMinutes % 60;

            // Safety: ensure we don't exceed 23:59
            if ($endLocalHour > 23) {
                $endLocalHour = 23;
                $endLocalMinute = 59;
            }

            $date = $faker->dateTimeBetween('+1 day', '+30 days')->format('Y-m-d');
            $startLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $startLocalHour, $startLocalMinute),
                $timezone
            );
            $endLocal = Carbon::createFromFormat(
                'Y-m-d H:i:s',
                sprintf('%s %02d:%02d:00', $date, $endLocalHour, $endLocalMinute),
                $timezone
            );

            $startUtc = $startLocal->copy()->setTimezone('UTC');
            $endUtc = $endLocal->copy()->setTimezone('UTC');

            $cases["end_after_closing_{$i}_tz_{$timezone}"] = [
                $timezone,
                $openingTime,
                $closingTime,
                $startUtc->toDateTimeString(),
                $endUtc->toDateTimeString(),
                false,
            ];
        }

        return $cases;
    }

    /**
     * **Validates: Requirements 6.1**
     */
    #[DataProvider('validAppointmentsProvider')]
    public function test_accepts_appointment_within_operating_hours(
        string $timezone,
        string $openingTime,
        string $closingTime,
        string $startUtc,
        string $endUtc,
        bool $expectedPass,
    ): void {
        $branch = Branch::create([
            'name' => 'Branch ' . uniqid(),
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => $timezone,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
        ]);

        $rule = new OperatingHoursRule(
            branch: $branch,
            startUtc: Carbon::parse($startUtc, 'UTC'),
            endUtc: Carbon::parse($endUtc, 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty(
            $errors,
            "Expected appointment to be accepted within operating hours.\n"
            . "Timezone: {$timezone}, Opening: {$openingTime}, Closing: {$closingTime}\n"
            . "Start UTC: {$startUtc}, End UTC: {$endUtc}\n"
            . "Errors: " . implode(', ', $errors)
        );
    }

    /**
     * **Validates: Requirements 6.2**
     */
    #[DataProvider('invalidStartBeforeOpeningProvider')]
    public function test_rejects_appointment_starting_before_opening(
        string $timezone,
        string $openingTime,
        string $closingTime,
        string $startUtc,
        string $endUtc,
        bool $expectedPass,
    ): void {
        $branch = Branch::create([
            'name' => 'Branch ' . uniqid(),
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => $timezone,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
        ]);

        $rule = new OperatingHoursRule(
            branch: $branch,
            startUtc: Carbon::parse($startUtc, 'UTC'),
            endUtc: Carbon::parse($endUtc, 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertNotEmpty(
            $errors,
            "Expected appointment to be rejected (start before opening).\n"
            . "Timezone: {$timezone}, Opening: {$openingTime}, Closing: {$closingTime}\n"
            . "Start UTC: {$startUtc}, End UTC: {$endUtc}"
        );

        // Verify the error message mentions start time and opening hours
        $hasStartError = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'start time is before branch opening hours')) {
                $hasStartError = true;
                break;
            }
        }
        $this->assertTrue($hasStartError, "Error message should indicate start time is before opening hours.");
    }

    /**
     * **Validates: Requirements 6.3**
     */
    #[DataProvider('invalidEndAfterClosingProvider')]
    public function test_rejects_appointment_ending_after_closing(
        string $timezone,
        string $openingTime,
        string $closingTime,
        string $startUtc,
        string $endUtc,
        bool $expectedPass,
    ): void {
        $branch = Branch::create([
            'name' => 'Branch ' . uniqid(),
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => $timezone,
            'opening_time' => $openingTime,
            'closing_time' => $closingTime,
        ]);

        $rule = new OperatingHoursRule(
            branch: $branch,
            startUtc: Carbon::parse($startUtc, 'UTC'),
            endUtc: Carbon::parse($endUtc, 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertNotEmpty(
            $errors,
            "Expected appointment to be rejected (end after closing).\n"
            . "Timezone: {$timezone}, Opening: {$openingTime}, Closing: {$closingTime}\n"
            . "Start UTC: {$startUtc}, End UTC: {$endUtc}"
        );

        // Verify the error message mentions end time and closing hours
        $hasEndError = false;
        foreach ($errors as $error) {
            if (str_contains($error, 'end time is after branch closing hours')) {
                $hasEndError = true;
                break;
            }
        }
        $this->assertTrue($hasEndError, "Error message should indicate end time is after closing hours.");
    }
}
