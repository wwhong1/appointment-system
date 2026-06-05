<?php

namespace Tests\Feature\Properties;

use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Models\User;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 3: Service duration bounds validation
 *
 * For any integer value, the service duration validation SHALL accept it if and only if
 * it is in the range [1, 480], and SHALL reject all values outside this range.
 *
 * Validates: Requirements 2.4
 */
#[Group('property')]
#[Group('appointment-scheduling')]
class ServiceDurationValidationTest extends TestCase
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
     * Property: Any duration value in [1, 480] SHALL be accepted by the ServiceResource form.
     */
    #[Test]
    public function valid_durations_in_range_1_to_480_are_always_accepted(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $duration = $faker->numberBetween(1, 480);
            $name = 'Service_' . $i . '_' . $faker->unique()->word();

            Livewire::test(CreateService::class)
                ->fillForm([
                    'name' => $name,
                    'duration_minutes' => $duration,
                ])
                ->call('create')
                ->assertHasNoFormErrors();

            $this->assertDatabaseHas('services', [
                'name' => $name,
                'duration_minutes' => $duration,
            ]);
        }
    }

    /**
     * Property: Any duration value less than 1 SHALL be rejected by the ServiceResource form.
     */
    #[Test]
    public function durations_below_1_are_always_rejected(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $duration = $faker->numberBetween(-1000, 0);
            $name = 'Service_below_' . $i . '_' . $faker->unique()->word();

            Livewire::test(CreateService::class)
                ->fillForm([
                    'name' => $name,
                    'duration_minutes' => $duration,
                ])
                ->call('create')
                ->assertHasFormErrors(['duration_minutes']);

            $this->assertDatabaseMissing('services', [
                'name' => $name,
            ]);
        }
    }

    /**
     * Property: Any duration value greater than 480 SHALL be rejected by the ServiceResource form.
     */
    #[Test]
    public function durations_above_480_are_always_rejected(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            $duration = $faker->numberBetween(481, 10000);
            $name = 'Service_above_' . $i . '_' . $faker->unique()->word();

            Livewire::test(CreateService::class)
                ->fillForm([
                    'name' => $name,
                    'duration_minutes' => $duration,
                ])
                ->call('create')
                ->assertHasFormErrors(['duration_minutes']);

            $this->assertDatabaseMissing('services', [
                'name' => $name,
            ]);
        }
    }

    /**
     * Property: For any random integer, the form accepts it if and only if it is in [1, 480].
     * This is the oracle property - comparing form behavior against the spec bounds.
     */
    #[Test]
    public function duration_acceptance_matches_bounds_oracle(): void
    {
        $faker = Faker::create();

        for ($i = 0; $i < self::ITERATIONS; $i++) {
            // Generate random integers spanning valid and invalid ranges
            $duration = $faker->numberBetween(-500, 1000);
            $name = 'Service_oracle_' . $i . '_' . $faker->unique()->word();
            $shouldBeValid = ($duration >= 1 && $duration <= 480);

            $response = Livewire::test(CreateService::class)
                ->fillForm([
                    'name' => $name,
                    'duration_minutes' => $duration,
                ])
                ->call('create');

            if ($shouldBeValid) {
                $response->assertHasNoFormErrors();

                $this->assertDatabaseHas('services', [
                    'name' => $name,
                    'duration_minutes' => $duration,
                ]);
            } else {
                $response->assertHasFormErrors(['duration_minutes']);

                $this->assertDatabaseMissing('services', [
                    'name' => $name,
                ]);
            }
        }
    }

    /**
     * Property: Boundary values 1 and 480 SHALL always be accepted.
     */
    #[Test]
    public function boundary_values_are_accepted(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Boundary Min Service',
                'duration_minutes' => 1,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Boundary Min Service',
            'duration_minutes' => 1,
        ]);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Boundary Max Service',
                'duration_minutes' => 480,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Boundary Max Service',
            'duration_minutes' => 480,
        ]);
    }

    /**
     * Property: Values immediately outside boundaries (0 and 481) SHALL always be rejected.
     */
    #[Test]
    public function values_immediately_outside_boundaries_are_rejected(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Just Below Min Service',
                'duration_minutes' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_minutes']);

        $this->assertDatabaseMissing('services', [
            'name' => 'Just Below Min Service',
        ]);

        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Just Above Max Service',
                'duration_minutes' => 481,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_minutes']);

        $this->assertDatabaseMissing('services', [
            'name' => 'Just Above Max Service',
        ]);
    }
}
