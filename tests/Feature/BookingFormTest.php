<?php

namespace Tests\Feature;

use App\Livewire\BookingForm;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BookingFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_booking_form_page_is_publicly_accessible(): void
    {
        $this->withoutVite();

        $response = $this->get('/book');

        $response->assertStatus(200);
        $response->assertSeeLivewire(BookingForm::class);
    }

    public function test_booking_form_displays_branches(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        Livewire::test(BookingForm::class)
            ->assertSee('Main Branch');
    }

    public function test_staff_options_filtered_by_branch(): void
    {
        $branch1 = Branch::create([
            'name' => 'Branch One',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch Two',
            'address' => '456 Other St',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff1 = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch1->id,
        ]);

        $staff2 = User::create([
            'name' => 'Staff Two',
            'email' => 'staff2@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch2->id,
        ]);

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch1->id)
            ->assertSee('Staff One')
            ->assertDontSee('Staff Two');
    }

    public function test_staff_id_resets_when_branch_changes(): void
    {
        $branch1 = Branch::create([
            'name' => 'Branch One',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch Two',
            'address' => '456 Other St',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff1 = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch1->id,
        ]);

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch1->id)
            ->set('staff_id', $staff1->id)
            ->assertSet('staff_id', $staff1->id)
            ->set('branch_id', $branch2->id)
            ->assertSet('staff_id', null);
    }

    public function test_validation_requires_at_least_one_contact_method(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('customer_name', 'John Doe')
            ->set('customer_email', '')
            ->set('customer_phone', '')
            ->call('submit')
            ->assertHasErrors(['customer_email', 'customer_phone']);
    }

    public function test_validation_passes_with_email_only(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('customer_name', 'John Doe')
            ->set('customer_email', 'john@example.com')
            ->set('customer_phone', '')
            ->call('submit')
            ->assertHasNoErrors();
    }

    public function test_validation_passes_with_phone_only(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', now()->addDay()->format('Y-m-d\TH:i'))
            ->set('customer_name', 'John Doe')
            ->set('customer_email', '')
            ->set('customer_phone', '+60123456789')
            ->call('submit')
            ->assertHasNoErrors();
    }

    public function test_successful_booking_creates_appointment_and_customer(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        // 10:00 AM local (Asia/Kuala_Lumpur) - form input is branch-local time
        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'Jane Doe')
            ->set('customer_email', 'jane@example.com')
            ->set('customer_phone', '+60198765432')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bookingSuccess', true)
            ->assertSet('successMessage', 'Your appointment has been booked successfully!');

        // Verify customer was created
        $this->assertDatabaseHas('customers', [
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'phone' => '+60198765432',
        ]);

        // Verify appointment was created with pending status
        $customer = Customer::where('email', 'jane@example.com')->first();
        $this->assertDatabaseHas('appointments', [
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'status' => 'pending',
        ]);
    }

    public function test_booking_associates_with_existing_customer_by_email(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        // Pre-existing customer
        $existingCustomer = Customer::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'phone' => '+60111111111',
        ]);

        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'Different Name')
            ->set('customer_email', 'existing@example.com')
            ->set('customer_phone', '+60199999999')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bookingSuccess', true);

        // Should not create a new customer
        $this->assertEquals(1, Customer::where('email', 'existing@example.com')->count());

        // Appointment should be associated with existing customer
        $this->assertDatabaseHas('appointments', [
            'customer_id' => $existingCustomer->id,
            'branch_id' => $branch->id,
        ]);
    }

    public function test_booking_associates_with_existing_customer_by_phone(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        // Pre-existing customer with phone only
        $existingCustomer = Customer::create([
            'name' => 'Phone Customer',
            'email' => null,
            'phone' => '+60198765432',
        ]);

        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'Another Name')
            ->set('customer_email', '')
            ->set('customer_phone', '+60198765432')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bookingSuccess', true);

        // Should not create a new customer
        $this->assertEquals(1, Customer::where('phone', '+60198765432')->count());

        // Appointment should be associated with existing customer
        $this->assertDatabaseHas('appointments', [
            'customer_id' => $existingCustomer->id,
        ]);
    }

    public function test_booking_displays_validation_error_for_staff_not_at_branch(): void
    {
        $branch1 = Branch::create([
            'name' => 'Branch One',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $branch2 = Branch::create([
            'name' => 'Branch Two',
            'address' => '456 Other St',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        // Staff assigned to branch2 but we'll try to book at branch1
        $staff = User::create([
            'name' => 'Staff Two',
            'email' => 'staff2@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch2->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch1->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'John Doe')
            ->set('customer_email', 'john@example.com')
            ->call('submit')
            ->assertHasErrors(['staff_id']);
    }

    public function test_booking_displays_validation_error_for_overlap(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        $customer = Customer::create([
            'name' => 'Existing Customer',
            'email' => 'existing@example.com',
            'phone' => '+60111111111',
        ]);

        // Create an existing appointment at 02:00-02:30 UTC (10:00-10:30 local) tomorrow
        $startTime = now()->addDay()->setTime(2, 0);
        Appointment::create([
            'branch_id' => $branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $customer->id,
            'service_id' => $service->id,
            'start_datetime' => $startTime,
            'end_datetime' => $startTime->copy()->addMinutes(30),
            'status' => 'pending',
        ]);

        // Try to book at the same time (overlapping) - 10:00 local
        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'New Customer')
            ->set('customer_email', 'new@example.com')
            ->call('submit')
            ->assertHasErrors(['start_datetime']);
    }

    public function test_booking_displays_validation_error_for_outside_operating_hours(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        // Try to book at 18:00 local time (after 17:00 closing)
        $startDatetime = now()->addDay()->setTime(18, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'John Doe')
            ->set('customer_email', 'john@example.com')
            ->call('submit')
            ->assertHasErrors(['end_datetime']);
    }

    public function test_form_resets_after_successful_booking(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'Jane Doe')
            ->set('customer_email', 'jane@example.com')
            ->set('customer_phone', '')
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('bookingSuccess', true)
            ->assertSet('branch_id', null)
            ->assertSet('staff_id', null)
            ->assertSet('service_id', null)
            ->assertSet('start_datetime', '')
            ->assertSet('customer_name', '')
            ->assertSet('customer_email', '')
            ->assertSet('customer_phone', '');
    }

    public function test_success_message_displayed_in_view(): void
    {
        $branch = Branch::create([
            'name' => 'Main Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '17:00',
        ]);

        $staff = User::create([
            'name' => 'Staff One',
            'email' => 'staff1@example.com',
            'password' => bcrypt('password'),
            'role' => 'staff',
            'branch_id' => $branch->id,
        ]);

        $service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        $startDatetime = now()->addDay()->setTime(10, 0)->format('Y-m-d\TH:i');

        Livewire::test(BookingForm::class)
            ->set('branch_id', $branch->id)
            ->set('staff_id', $staff->id)
            ->set('service_id', $service->id)
            ->set('start_datetime', $startDatetime)
            ->set('customer_name', 'Jane Doe')
            ->set('customer_email', 'jane@example.com')
            ->set('customer_phone', '')
            ->call('submit')
            ->assertSee('Your appointment has been booked successfully!');
    }
}
