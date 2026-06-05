<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\ServiceResource;
use App\Filament\Resources\ServiceResource\Pages\CreateService;
use App\Filament\Resources\ServiceResource\Pages\EditService;
use App\Filament\Resources\ServiceResource\Pages\ListServices;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ServiceResourceTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->actingAs($this->admin);
    }

    public function test_can_list_services(): void
    {
        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'price' => 25.00,
        ]);

        Livewire::test(ListServices::class)
            ->assertCanSeeTableRecords([$service]);
    }

    public function test_can_create_service_with_required_fields(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Massage',
                'duration_minutes' => 60,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Massage',
            'duration_minutes' => 60,
        ]);
    }

    public function test_can_create_service_with_all_fields(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Full Body Massage',
                'duration_minutes' => 90,
                'price' => 150.50,
                'description' => 'A relaxing full body massage session.',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'name' => 'Full Body Massage',
            'duration_minutes' => 90,
            'price' => 150.50,
            'description' => 'A relaxing full body massage session.',
        ]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => '',
                'duration_minutes' => 30,
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_duration_is_required(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_minutes' => 'required']);
    }

    public function test_duration_minimum_is_1(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_minutes']);
    }

    public function test_duration_maximum_is_480(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => 481,
            ])
            ->call('create')
            ->assertHasFormErrors(['duration_minutes']);
    }

    public function test_price_minimum_is_001(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => 30,
                'price' => 0,
            ])
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    public function test_price_maximum_is_999999_99(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => 30,
                'price' => 1000000,
            ])
            ->call('create')
            ->assertHasFormErrors(['price']);
    }

    public function test_description_maximum_is_1000_characters(): void
    {
        Livewire::test(CreateService::class)
            ->fillForm([
                'name' => 'Test Service',
                'duration_minutes' => 30,
                'description' => str_repeat('a', 1001),
            ])
            ->call('create')
            ->assertHasFormErrors(['description']);
    }

    public function test_can_edit_service(): void
    {
        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'price' => 25.00,
        ]);

        Livewire::test(EditService::class, ['record' => $service->getRouteKey()])
            ->fillForm([
                'name' => 'Premium Haircut',
                'duration_minutes' => 45,
                'price' => 35.00,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
            'name' => 'Premium Haircut',
            'duration_minutes' => 45,
            'price' => 35.00,
        ]);
    }

    public function test_cannot_delete_service_with_appointments(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        $staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'price' => 25.00,
        ]);

        Appointment::create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'start_datetime' => now()->addDay(),
            'end_datetime' => now()->addDay()->addMinutes(30),
            'status' => 'pending',
        ]);

        // The service should still exist after attempting deletion
        Livewire::test(ListServices::class)
            ->callTableAction(\Filament\Actions\DeleteAction::class, $service);

        $this->assertDatabaseHas('services', [
            'id' => $service->id,
        ]);
    }

    public function test_can_delete_service_without_appointments(): void
    {
        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
            'price' => 25.00,
        ]);

        Livewire::test(ListServices::class)
            ->callTableAction(\Filament\Actions\DeleteAction::class, $service);

        $this->assertDatabaseMissing('services', [
            'id' => $service->id,
        ]);
    }
}
