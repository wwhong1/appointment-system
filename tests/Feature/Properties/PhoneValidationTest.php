<?php

namespace Tests\Feature\Properties;

use App\Rules\E164PhoneRule;
use Faker\Factory as Faker;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 2: E.164 phone number validation
 *
 * For any string, the phone validation SHALL accept it if and only if it matches
 * the E.164 pattern (+ followed by 1-15 digits, first digit 1-9), and SHALL
 * reject all other strings.
 *
 * Validates: Requirements 1.4, 4.2
 */
#[Group('property')]
#[Group('appointment-scheduling')]
class PhoneValidationTest extends TestCase
{
    private const ITERATIONS = 100;

    private E164PhoneRule $rule;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rule = new E164PhoneRule();
    }

    private function passes(mixed $value): bool
    {
        $passed = true;
        $this->rule->validate('phone', $value, function () use (&$passed) {
            $passed = false;
        });

        return $passed;
    }

    /**
     * Generate a random valid E.164 phone number:
     * + followed by 1-15 digits, first digit must be 1-9.
     */
    private function generateValidE164(): string
    {
        $faker = Faker::create();

        // First digit must be 1-9
        $firstDigit = (string) $faker->numberBetween(1, 9);

        // Remaining digits: 0-14 more digits (total 1-15 digits)
        $remainingLength = $faker->numberBetween(0, 14);
        $remainingDigits = '';
        for ($i = 0; $i < $remainingLength; $i++) {
            $remainingDigits .= (string) $faker->numberBetween(0, 9);
        }

        return '+' . $firstDigit . $remainingDigits;
    }

    /**
     * Property: Any valid E.164 number (+ followed by 1-15 digits, first digit 1-9)
     * SHALL be accepted by the rule.
     */
    #[Test]
    public function valid_e164_numbers_are_always_accepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $phone = $this->generateValidE164();

            $this->assertTrue(
                $this->passes($phone),
                "Iteration {$i}: Expected valid E.164 number '{$phone}' to be accepted."
            );
        }
    }

    /**
     * Property: Any string missing the leading '+' prefix SHALL be rejected.
     */
    #[Test]
    public function numbers_without_plus_prefix_are_always_rejected(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate digits that would be valid after a +, but omit the +
            $length = $faker->numberBetween(1, 15);
            $firstDigit = (string) $faker->numberBetween(1, 9);
            $rest = '';
            for ($j = 0; $j < $length - 1; $j++) {
                $rest .= (string) $faker->numberBetween(0, 9);
            }
            $phone = $firstDigit . $rest;

            $this->assertFalse(
                $this->passes($phone),
                "Iteration {$i}: Expected number without '+' prefix '{$phone}' to be rejected."
            );
        }
    }

    /**
     * Property: Any string with more than 15 digits after '+' SHALL be rejected.
     */
    #[Test]
    public function numbers_exceeding_15_digits_are_always_rejected(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate 16-30 digits
            $length = $faker->numberBetween(16, 30);
            $firstDigit = (string) $faker->numberBetween(1, 9);
            $rest = '';
            for ($j = 0; $j < $length - 1; $j++) {
                $rest .= (string) $faker->numberBetween(0, 9);
            }
            $phone = '+' . $firstDigit . $rest;

            $this->assertFalse(
                $this->passes($phone),
                "Iteration {$i}: Expected number with {$length} digits '{$phone}' to be rejected."
            );
        }
    }

    /**
     * Property: Any string starting with '+0' SHALL be rejected (first digit after + must be 1-9).
     */
    #[Test]
    public function numbers_with_leading_zero_after_plus_are_always_rejected(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate +0 followed by 0-14 random digits
            $remainingLength = $faker->numberBetween(0, 14);
            $remainingDigits = '';
            for ($j = 0; $j < $remainingLength; $j++) {
                $remainingDigits .= (string) $faker->numberBetween(0, 9);
            }
            $phone = '+0' . $remainingDigits;

            $this->assertFalse(
                $this->passes($phone),
                "Iteration {$i}: Expected number with leading zero '{$phone}' to be rejected."
            );
        }
    }

    /**
     * Property: Any string containing non-digit characters (after the +) SHALL be rejected.
     */
    #[Test]
    public function numbers_with_non_digit_characters_are_always_rejected(): void
    {
        $faker = Faker::create();
        $nonDigitChars = ['a', 'b', 'z', 'A', 'Z', ' ', '-', '(', ')', '.', '#', '*', '@'];

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Start with a valid-looking number and inject a non-digit character
            $length = $faker->numberBetween(1, 14);
            $digits = '';
            for ($j = 0; $j < $length; $j++) {
                $digits .= (string) $faker->numberBetween(0, 9);
            }

            // Insert a random non-digit character at a random position
            $insertPos = $faker->numberBetween(0, strlen($digits));
            $nonDigit = $faker->randomElement($nonDigitChars);
            $corrupted = substr($digits, 0, $insertPos) . $nonDigit . substr($digits, $insertPos);

            $phone = '+' . $corrupted;

            $this->assertFalse(
                $this->passes($phone),
                "Iteration {$i}: Expected number with non-digit char '{$phone}' to be rejected."
            );
        }
    }

    /**
     * Property: Non-string values SHALL always be rejected.
     */
    #[Test]
    public function non_string_values_are_always_rejected(): void
    {
        $faker = Faker::create();

        $nonStringValues = [];
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $type = $faker->numberBetween(0, 4);
            $nonStringValues[] = match ($type) {
                0 => $faker->numberBetween(-999999, 999999),       // integers
                1 => $faker->randomFloat(2, -9999, 9999),          // floats
                2 => null,                                          // null
                3 => $faker->boolean(),                             // booleans
                4 => [$faker->word()],                              // arrays
            };
        }

        foreach ($nonStringValues as $i => $value) {
            $this->assertFalse(
                $this->passes($value),
                "Iteration {$i}: Expected non-string value to be rejected. Got: " . var_export($value, true)
            );
        }
    }

    /**
     * Property: The empty string and '+' alone SHALL be rejected.
     */
    #[Test]
    public function empty_and_plus_only_strings_are_rejected(): void
    {
        $this->assertFalse($this->passes(''), "Empty string should be rejected.");
        $this->assertFalse($this->passes('+'), "'+' alone should be rejected.");
    }

    /**
     * Property: For any random string, the rule accepts it if and only if it matches
     * the E.164 regex pattern (+ followed by 1-15 digits, first digit 1-9).
     * This is the oracle property - comparing rule behavior against the spec regex.
     */
    #[Test]
    public function rule_matches_e164_regex_oracle_for_random_strings(): void
    {
        $faker = Faker::create();
        $e164Pattern = '/^\+[1-9]\d{0,14}$/';

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate a variety of random strings
            $type = $faker->numberBetween(0, 5);
            $input = match ($type) {
                0 => $this->generateValidE164(),                                    // valid E.164
                1 => $faker->numerify(str_repeat('#', $faker->numberBetween(1, 20))), // random digits
                2 => '+' . $faker->lexify(str_repeat('?', $faker->numberBetween(1, 15))), // + with letters
                3 => $faker->bothify('+##??##??'),                                  // mixed
                4 => $faker->regexify('[+0-9a-z ()-]{0,20}'),                       // random phone-like
                5 => $faker->word(),                                                 // random word
            };

            $expectedResult = is_string($input) && preg_match($e164Pattern, $input) === 1;
            $actualResult = $this->passes($input);

            $this->assertSame(
                $expectedResult,
                $actualResult,
                "Iteration {$i}: Oracle mismatch for input '{$input}'. " .
                "Expected " . ($expectedResult ? 'accepted' : 'rejected') .
                " but got " . ($actualResult ? 'accepted' : 'rejected') . "."
            );
        }
    }
}
