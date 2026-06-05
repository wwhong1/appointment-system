<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\BranchResource\Pages\CreateBranch;
use App\Filament\Resources\BranchResource\Pages\EditBranch;
use App\Filament\Resources\BranchResource\Pages\ListBranches;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BranchResourceTest extends TestCase
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

    public function test_can_list_branches(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        Livewire::test(ListBranches::class)
            ->assertCanSeeTableRecords([$branch]);
    }

    public function test_can_create_branch_with_valid_data(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Downtown Branch',
                'address' => '456 Downtown Ave',
                'phone' => '+60198765432',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '08:00',
                'closing_time' => '17:00',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('branches', [
            'name' => 'Downtown Branch',
            'address' => '456 Downtown Ave',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
        ]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => '',
                'address' => '123 Main St',
                'phone' => '+60123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_name_must_be_unique(): void
    {
        Branch::create([
            'name' => 'Existing Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Existing Branch',
                'address' => '456 Other St',
                'phone' => '+60198765432',
                'timezone' => 'UTC',
                'opening_time' => '08:00',
                'closing_time' => '17:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'unique']);
    }

    public function test_address_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '',
                'phone' => '+60123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['address' => 'required']);
    }

    public function test_phone_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone' => 'required']);
    }

    public function test_phone_must_be_e164_format(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '0123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone']);
    }

    public function test_timezone_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '+60123456789',
                'timezone' => null,
                'opening_time' => '09:00',
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['timezone' => 'required']);
    }

    public function test_opening_time_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '+60123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => null,
                'closing_time' => '18:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['opening_time' => 'required']);
    }

    public function test_closing_time_is_required(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '+60123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '09:00',
                'closing_time' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['closing_time' => 'required']);
    }

    public function test_closing_time_must_be_after_opening_time(): void
    {
        Livewire::test(CreateBranch::class)
            ->fillForm([
                'name' => 'Test Branch',
                'address' => '123 Main St',
                'phone' => '+60123456789',
                'timezone' => 'Asia/Kuala_Lumpur',
                'opening_time' => '18:00',
                'closing_time' => '09:00',
            ])
            ->call('create')
            ->assertHasFormErrors(['closing_time']);
    }

    public function test_can_edit_branch(): void
    {
        $branch = Branch::create([
            'name' => 'Original Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        Livewire::test(EditBranch::class, ['record' => $branch->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Branch',
                'address' => '789 New St',
                'phone' => '+60198765432',
                'timezone' => 'UTC',
                'opening_time' => '08:00',
                'closing_time' => '20:00',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
            'name' => 'Updated Branch',
            'address' => '789 New St',
            'phone' => '+60198765432',
            'timezone' => 'UTC',
        ]);
    }

    public function test_unique_name_ignores_self_on_edit(): void
    {
        $branch = Branch::create([
            'name' => 'My Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        Livewire::test(EditBranch::class, ['record' => $branch->getRouteKey()])
            ->fillForm([
                'name' => 'My Branch',
                'address' => '456 Updated St',
            ])
            ->call('save')
            ->assertHasNoFormErrors();
    }

    public function test_cannot_delete_branch_with_appointments(): void
    {
        $branch = Branch::create([
            'name' => 'Branch With Appointments',
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

        Livewire::test(ListBranches::class)
            ->callTableAction(DeleteAction::class, $branch);

        $this->assertDatabaseHas('branches', [
            'id' => $branch->id,
        ]);
    }

    public function test_can_delete_branch_without_appointments(): void
    {
        $branch = Branch::create([
            'name' => 'Empty Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        Livewire::test(ListBranches::class)
            ->callTableAction(DeleteAction::class, $branch);

        $this->assertDatabaseMissing('branches', [
            'id' => $branch->id,
        ]);
    }
}
