<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CustomerResource\Pages\CreateCustomer;
use App\Filament\Resources\CustomerResource\Pages\EditCustomer;
use App\Filament\Resources\CustomerResource\Pages\ListCustomers;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CustomerResourceTest extends TestCase
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

    public function test_can_list_customers(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Livewire::test(ListCustomers::class)
            ->assertCanSeeTableRecords([$customer]);
    }

    public function test_can_create_customer_with_email(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
        ]);
    }

    public function test_can_create_customer_with_phone(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => '',
                'phone' => '+60123456789',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'phone' => '+60123456789',
        ]);
    }

    public function test_can_create_customer_with_both_contacts(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'jane@example.com',
                'phone' => '+60123456789',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+60123456789',
        ]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => '',
                'email' => 'jane@example.com',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_at_least_one_contact_method_required(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => '',
                'phone' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['email', 'phone']);
    }

    public function test_email_must_be_valid_format(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => 'not-an-email',
                'phone' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['email']);
    }

    public function test_phone_must_be_e164_format(): void
    {
        Livewire::test(CreateCustomer::class)
            ->fillForm([
                'name' => 'Jane Doe',
                'email' => '',
                'phone' => '0123456789',
            ])
            ->call('create')
            ->assertHasFormErrors(['phone']);
    }

    public function test_can_edit_customer(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Livewire::test(EditCustomer::class, ['record' => $customer->getRouteKey()])
            ->fillForm([
                'name' => 'John Smith',
                'email' => 'john.smith@example.com',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
            'name' => 'John Smith',
            'email' => 'john.smith@example.com',
        ]);
    }

    public function test_cannot_delete_customer_with_appointments(): void
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

        Livewire::test(ListCustomers::class)
            ->callTableAction(DeleteAction::class, $customer);

        $this->assertDatabaseHas('customers', [
            'id' => $customer->id,
        ]);
    }

    public function test_can_delete_customer_without_appointments(): void
    {
        $customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        Livewire::test(ListCustomers::class)
            ->callTableAction(DeleteAction::class, $customer);

        $this->assertDatabaseMissing('customers', [
            'id' => $customer->id,
        ]);
    }
}
