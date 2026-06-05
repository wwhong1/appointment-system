<?php

namespace Tests\Feature\Properties;

use App\Filament\Resources\BranchResource\Pages\CreateBranch;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 1: Branch opening time must precede closing time
 *
 * For any pair of times (opening, closing), the system SHALL accept the branch
 * only when opening is strictly earlier than closing, and SHALL reject it otherwise.
 *
 * Validates: Requirements 1.3
 */
#[Group('property')]
#[Group('appointment-scheduling')]
class BranchTimeValidationTest extends TestCase
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
     * Generate a random time string in HH:MM format.
     */
    private function generateRandomTime(\Faker\Generator $faker): string
    {
        $hour = $faker->numberBetween(0, 23);
        $minute = $faker->numberBetween(0, 59);

        return sprintf('%02d:%02d', $hour, $minute);
    }

    /**
     * Generate a unique branch name for each iteration to avoid unique constraint violations.
     */
    private function generateBranchName(int $iteration): string
    {
        return "Branch Iteration {$iteration} " . uniqid();
    }

    /**
     * Property: When opening time is strictly earlier than closing time,
     * branch creation SHALL succeed.
     */
    #[Test]
    public function branch_creation_succeeds_when_opening_is_before_closing(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate opening time that allows room for a later closing time
            $openingHour = $faker->numberBetween(0, 22);
            $openingMinute = $faker->numberBetween(0, 59);

            // Closing must be strictly after opening
            if ($openingHour === 22 && $openingMinute === 59) {
                // Edge: opening is 22:59, closing must be 23:xx
                $closingHour = 23;
                $closingMinute = $faker->numberBetween(0, 59);
            } elseif ($openingHour === 23) {
                // If opening is 23:xx, closing can only be later in the same hour
                $openingHour = $faker->numberBetween(0, 22);
                $openingMinute = $faker->numberBetween(0, 58);
                $closingHour = $faker->numberBetween($openingHour, 23);
                if ($closingHour === $openingHour) {
                    $closingMinute = $faker->numberBetween($openingMinute + 1, 59);
                } else {
                    $closingMinute = $faker->numberBetween(0, 59);
                }
            } else {
                // Normal case: closing hour is same or later
                $closingHour = $faker->numberBetween($openingHour, 23);
                if ($closingHour === $openingHour) {
                    // Same hour: closing minute must be strictly greater
                    if ($openingMinute >= 59) {
                        $closingHour = $faker->numberBetween($openingHour + 1, 23);
                        $closingMinute = $faker->numberBetween(0, 59);
                    } else {
                        $closingMinute = $faker->numberBetween($openingMinute + 1, 59);
                    }
                } else {
                    $closingMinute = $faker->numberBetween(0, 59);
                }
            }

            $openingTime = sprintf('%02d:%02d', $openingHour, $openingMinute);
            $closingTime = sprintf('%02d:%02d', $closingHour, $closingMinute);

            // Sanity check: opening must be strictly before closing
            $this->assertTrue(
                $openingTime < $closingTime,
                "Generator error at iteration {$i}: opening={$openingTime} should be < closing={$closingTime}"
            );

            Livewire::test(CreateBranch::class)
                ->fillForm([
                    'name' => $this->generateBranchName($i),
                    'address' => '123 Test Street',
                    'phone' => '+60123456789',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'opening_time' => $openingTime,
                    'closing_time' => $closingTime,
                ])
                ->call('create')
                ->assertHasNoFormErrors();
        }
    }

    /**
     * Property: When opening time is equal to closing time,
     * branch creation SHALL be rejected with a validation error on closing_time.
     */
    #[Test]
    public function branch_creation_fails_when_opening_equals_closing(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $time = $this->generateRandomTime($faker);

            Livewire::test(CreateBranch::class)
                ->fillForm([
                    'name' => $this->generateBranchName($i),
                    'address' => '123 Test Street',
                    'phone' => '+60123456789',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'opening_time' => $time,
                    'closing_time' => $time,
                ])
                ->call('create')
                ->assertHasFormErrors(['closing_time']);
        }
    }

    /**
     * Property: When opening time is strictly later than closing time,
     * branch creation SHALL be rejected with a validation error on closing_time.
     */
    #[Test]
    public function branch_creation_fails_when_opening_is_after_closing(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate closing time that is strictly before opening time
            $closingHour = $faker->numberBetween(0, 22);
            $closingMinute = $faker->numberBetween(0, 59);

            // Opening must be strictly after closing
            $openingHour = $faker->numberBetween($closingHour, 23);
            if ($openingHour === $closingHour) {
                if ($closingMinute >= 59) {
                    $openingHour = $faker->numberBetween($closingHour + 1, 23);
                    $openingMinute = $faker->numberBetween(0, 59);
                } else {
                    $openingMinute = $faker->numberBetween($closingMinute + 1, 59);
                }
            } else {
                $openingMinute = $faker->numberBetween(0, 59);
            }

            $openingTime = sprintf('%02d:%02d', $openingHour, $openingMinute);
            $closingTime = sprintf('%02d:%02d', $closingHour, $closingMinute);

            // Sanity check: opening must be strictly after closing
            $this->assertTrue(
                $openingTime > $closingTime,
                "Generator error at iteration {$i}: opening={$openingTime} should be > closing={$closingTime}"
            );

            Livewire::test(CreateBranch::class)
                ->fillForm([
                    'name' => $this->generateBranchName($i),
                    'address' => '123 Test Street',
                    'phone' => '+60123456789',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'opening_time' => $openingTime,
                    'closing_time' => $closingTime,
                ])
                ->call('create')
                ->assertHasFormErrors(['closing_time']);
        }
    }

    /**
     * Property: For any random pair of times, the system accepts the branch
     * if and only if opening is strictly earlier than closing.
     * This is the oracle property - comparing system behavior against the spec.
     */
    #[Test]
    public function branch_time_validation_matches_oracle_for_random_pairs(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $openingTime = $this->generateRandomTime($faker);
            $closingTime = $this->generateRandomTime($faker);

            $shouldAccept = $openingTime < $closingTime;

            $component = Livewire::test(CreateBranch::class)
                ->fillForm([
                    'name' => $this->generateBranchName($i + self::ITERATIONS * 3),
                    'address' => '123 Test Street',
                    'phone' => '+60123456789',
                    'timezone' => 'Asia/Kuala_Lumpur',
                    'opening_time' => $openingTime,
                    'closing_time' => $closingTime,
                ])
                ->call('create');

            if ($shouldAccept) {
                $component->assertHasNoFormErrors();
            } else {
                $component->assertHasFormErrors(['closing_time']);
            }
        }
    }
}
