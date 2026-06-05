<?php

namespace Tests\Unit\Rules;

use App\Rules\E164PhoneRule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class E164PhoneRuleTest extends TestCase
{
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

    #[Test]
    #[DataProvider('validPhoneNumbers')]
    public function it_accepts_valid_e164_phone_numbers(string $phone): void
    {
        $this->assertTrue($this->passes($phone), "Expected '{$phone}' to be valid E.164");
    }

    #[Test]
    #[DataProvider('invalidPhoneNumbers')]
    public function it_rejects_invalid_phone_numbers(mixed $phone): void
    {
        $this->assertFalse($this->passes($phone), "Expected '{$phone}' to be invalid E.164");
    }

    #[Test]
    public function it_returns_correct_error_message(): void
    {
        $message = null;
        $this->rule->validate('phone', 'invalid', function ($msg) use (&$message) {
            $message = $msg;
        });

        $this->assertEquals(
            'Phone number must be in international format (e.g., +60123456789).',
            $message
        );
    }

    public static function validPhoneNumbers(): array
    {
        return [
            'Malaysia mobile' => ['+60123456789'],
            'US number' => ['+14155552671'],
            'UK number' => ['+442071234567'],
            'Minimum length (1 digit)' => ['+1'],
            'Maximum length (15 digits)' => ['+123456789012345'],
            'Single digit country code' => ['+61234567890'],
        ];
    }

    public static function invalidPhoneNumbers(): array
    {
        return [
            'missing plus' => ['60123456789'],
            'starts with zero after plus' => ['+0123456789'],
            'too many digits (16)' => ['+1234567890123456'],
            'contains letters' => ['+1234abc5678'],
            'contains spaces' => ['+60 123 456 789'],
            'contains dashes' => ['+60-123-456-789'],
            'empty string' => [''],
            'just plus sign' => ['+'],
            'integer value' => [60123456789],
            'null value' => [null],
            'contains parentheses' => ['+(60)123456789'],
            'double plus' => ['++60123456789'],
        ];
    }
}
