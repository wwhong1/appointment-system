<?php

namespace Tests\Feature\Properties;

use App\Livewire\BookingForm;
use App\Models\Customer;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 6: Customer lookup-or-create by contact match
 *
 * For any booking submission with customer details, if a customer record with
 * the same email or phone already exists, the system SHALL associate the booking
 * with the existing record; otherwise it SHALL create a new customer record.
 *
 * Validates: Requirements 4.6
 */
#[Group('property')]
#[Group('appointment-scheduling')]
class CustomerLookupOrCreateTest extends TestCase
{
    use RefreshDatabase;

    private const ITERATIONS = 100;

    /**
     * Generate a random valid E.164 phone number.
     */
    private function generateValidE164(): string
    {
        $faker = Faker::create();
        $firstDigit = (string) $faker->numberBetween(1, 9);
        $remainingLength = $faker->numberBetween(1, 14);
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
     * Invoke the protected lookupOrCreateCustomer method on BookingForm via reflection.
     */
    private function invokeLookupOrCreate(string $name, string $email, string $phone): Customer
    {
        $component = new BookingForm();
        $component->customer_name = $name;
        $component->customer_email = $email;
        $component->customer_phone = $phone;

        $reflection = new \ReflectionMethod($component, 'lookupOrCreateCustomer');
        $reflection->setAccessible(true);

        return $reflection->invoke($component);
    }

    /**
     * Property: When email matches an existing customer, the system SHALL use
     * the existing customer record (no new record created).
     *
     * Validates: Requirements 4.6
     */
    #[Test]
    public function existing_customer_is_found_by_email_match(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingEmail = $this->generateValidEmail();
            $existingName = $this->generateName();
            $existingPhone = $this->generateValidE164();

            // Create an existing customer with this email
            $existingCustomer = Customer::create([
                'name' => $existingName,
                'email' => $existingEmail,
                'phone' => $existingPhone,
            ]);

            $countBefore = Customer::count();

            // Invoke lookup with the same email but different name/phone
            $newName = $this->generateName();
            $newPhone = $this->generateValidE164();
            $result = $this->invokeLookupOrCreate($newName, $existingEmail, $newPhone);

            // Should return the existing customer, not create a new one
            $this->assertEquals($existingCustomer->id, $result->id,
                "Iteration $i: Expected existing customer ID {$existingCustomer->id} but got {$result->id} when email matches.");
            $this->assertEquals($countBefore, Customer::count(),
                "Iteration $i: No new customer record should be created when email matches.");
        }
    }

    /**
     * Property: When phone matches an existing customer (and email does not match),
     * the system SHALL use the existing customer record (no new record created).
     *
     * Validates: Requirements 4.6
     */
    #[Test]
    public function existing_customer_is_found_by_phone_match(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $existingPhone = $this->generateValidE164();
            $existingName = $this->generateName();
            $existingEmail = $this->generateValidEmail();

            // Create an existing customer with this phone
            $existingCustomer = Customer::create([
                'name' => $existingName,
                'email' => $existingEmail,
                'phone' => $existingPhone,
            ]);

            $countBefore = Customer::count();

            // Invoke lookup with the same phone but a different email
            $newName = $this->generateName();
            $newEmail = $this->generateValidEmail();
            $result = $this->invokeLookupOrCreate($newName, $newEmail, $existingPhone);

            // Should return the existing customer found by phone
            $this->assertEquals($existingCustomer->id, $result->id,
                "Iteration $i: Expected existing customer ID {$existingCustomer->id} but got {$result->id} when phone matches.");
            $this->assertEquals($countBefore, Customer::count(),
                "Iteration $i: No new customer record should be created when phone matches.");
        }
    }

    /**
     * Property: When neither email nor phone matches any existing customer,
     * the system SHALL create a new customer record.
     *
     * Validates: Requirements 4.6
     */
    #[Test]
    public function new_customer_is_created_when_no_match(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $countBefore = Customer::count();

            $name = $this->generateName();
            $email = $this->generateValidEmail();
            $phone = $this->generateValidE164();

            $result = $this->invokeLookupOrCreate($name, $email, $phone);

            // Should create a new customer
            $this->assertEquals($countBefore + 1, Customer::count(),
                "Iteration $i: A new customer record should be created when no match exists.");
            $this->assertEquals($name, $result->name,
                "Iteration $i: New customer should have the provided name.");
            $this->assertEquals($email, $result->email,
                "Iteration $i: New customer should have the provided email.");
            $this->assertEquals($phone, $result->phone,
                "Iteration $i: New customer should have the provided phone.");
        }
    }

    /**
     * Property: When both email and phone are provided and email matches an existing
     * customer, the system SHALL use the email-matched customer (email takes priority).
     *
     * Validates: Requirements 4.6
     */
    #[Test]
    public function email_match_takes_priority_over_phone_match(): void
    {
        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $emailCustomerEmail = $this->generateValidEmail();
            $phoneCustomerPhone = $this->generateValidE164();

            // Create a customer that matches by email
            $emailCustomer = Customer::create([
                'name' => $this->generateName(),
                'email' => $emailCustomerEmail,
                'phone' => $this->generateValidE164(),
            ]);

            // Create a different customer that matches by phone
            $phoneCustomer = Customer::create([
                'name' => $this->generateName(),
                'email' => $this->generateValidEmail(),
                'phone' => $phoneCustomerPhone,
            ]);

            $countBefore = Customer::count();

            // Invoke lookup with the email of the first customer and phone of the second
            $result = $this->invokeLookupOrCreate(
                $this->generateName(),
                $emailCustomerEmail,
                $phoneCustomerPhone
            );

            // Should return the email-matched customer (email takes priority)
            $this->assertEquals($emailCustomer->id, $result->id,
                "Iteration $i: Email match should take priority. Expected customer ID {$emailCustomer->id} but got {$result->id}.");
            $this->assertEquals($countBefore, Customer::count(),
                "Iteration $i: No new customer record should be created when email matches.");
        }
    }
}
