<?php

namespace Tests\Feature\Properties;

use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 5: Customer requires at least one contact method
 *
 * For any customer data submission, the system SHALL accept it if and only if
 * at least one of email or phone is provided and valid, and SHALL reject
 * submissions where both are absent.
 *
 * Validates: Requirements 4.1, 13.3
 */
#[Group('property')]
#[Group('appointment-scheduling')]
class CustomerContactRequirementTest extends TestCase
{
    use RefreshDatabase;

    private const ITERATIONS = 100;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($this->admin);
    }

    /**
     * Generate a random valid E.164 phone number.
     */
    private function generateValidE164(): string
    {
        $faker = Faker::create();
        $firstDigit = (string) $faker->numberBetween(1, 9);
        $remainingLength = $faker->numberBetween(0, 14);
        $remainingDigits = '';
        for ($i = 0; $i < $remainingLength; $i++) {
            $remainingDigits .= (string) $faker->numberBetween(0, 9);
        }

        return '+' . $firstDigit . $remainingDigits;
    }

    /**
     * Generate a random valid email address.
     */
    private function generateValidEmail(): string
    {
        $faker = Faker::create();

        return $faker->unique()->safeEmail();
    }

    /**
     * Generate a random customer name.
     */
    private function generateName(): string
    {
        $faker = Faker::create();

        return $faker->name();
    }

    /**
     * Property: Customer submissions with only a valid email (no phone) SHALL be accepted.
     *
     * Validates: Requirements 4.1, 13.3
     */
    #[Test]
    public function customer_with_email_only_is_accepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $this->generateName();
            $email = $this->generateValidEmail();

            Livewire::test(CreateCustomer::class)
                ->fillForm([
                    'name' => $name,
                    'email' => $email,
                    'phone' => '',
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('customers', [
                'name' => $name,
                'email' => $email,
            ]);
        }
    }

    /**
     * Property: Customer submissions with only a valid phone (no email) SHALL be accepted.
     *
     * Validates: Requirements 4.1, 13.3
     */
    #[Test]
    public function customer_with_phone_only_is_accepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $this->generateName();
            $phone = $this->generateValidE164();

            Livewire::test(CreateCustomer::class)
                ->fillForm([
                    'name' => $name,
                    'email' => '',
                    'phone' => $phone,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('customers', [
                'name' => $name,
                'phone' => $phone,
            ]);
        }
    }

    /**
     * Property: Customer submissions with both valid email and valid phone SHALL be accepted.
     *
     * Validates: Requirements 4.1, 13.3
     */
    #[Test]
    public function customer_with_both_email_and_phone_is_accepted(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $this->generateName();
            $email = $this->generateValidEmail();
            $phone = $this->generateValidE164();

            Livewire::test(CreateCustomer::class)
                ->fillForm([
                    'name' => $name,
                    'email' => $email,
                    'phone' => $phone,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('customers', [
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
            ]);
        }
    }

    /**
     * Property: Customer submissions with neither email nor phone SHALL be rejected.
     *
     * Validates: Requirements 4.1, 13.3
     */
    #[Test]
    public function customer_with_neither_email_nor_phone_is_rejected(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $name = $this->generateName();

            Livewire::test(CreateCustomer::class)
                ->fillForm([
                    'name' => $name,
                    'email' => '',
                    'phone' => '',
                ])
                ->call('create')
                ->assertHasFormErrors(['email', 'phone']);

            $this->assertDatabaseMissing('customers', [
                'name' => $name,
            ]);
        }
    }
}
