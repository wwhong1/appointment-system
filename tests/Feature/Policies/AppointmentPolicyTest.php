<?php

namespace Tests\Feature\Policies;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppointmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    protected AppointmentPolicy $policy;
    protected User $admin;
    protected User $staff;
    protected User $otherStaff;
    protected Branch $branch;
    protected Service $service;
    protected Customer $customer;
    protected Appointment $ownAppointment;
    protected Appointment $otherAppointment;

    protected function setUp(): void
    {
        parent::setUp();

        $this->policy = new AppointmentPolicy();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        $this->admin = User::factory()->create([
            'role' => 'admin',
        ]);

        $this->staff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->otherStaff = User::factory()->create([
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 30,
        ]);

        $this->customer = Customer::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->ownAppointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => now()->addDay(),
            'end_datetime' => now()->addDay()->addMinutes(30),
            'status' => 'pending',
        ]);

        $this->otherAppointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->otherStaff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => now()->addDays(2),
            'end_datetime' => now()->addDays(2)->addMinutes(30),
            'status' => 'pending',
        ]);
    }

    // --- Admin tests ---

    public function test_admin_can_view_any_appointments(): void
    {
        $this->assertTrue($this->policy->viewAny($this->admin));
    }

    public function test_admin_can_view_any_appointment(): void
    {
        $this->assertTrue($this->policy->view($this->admin, $this->ownAppointment));
        $this->assertTrue($this->policy->view($this->admin, $this->otherAppointment));
    }

    public function test_admin_can_create_appointments(): void
    {
        $this->assertTrue($this->policy->create($this->admin));
    }

    public function test_admin_can_update_any_appointment(): void
    {
        $this->assertTrue($this->policy->update($this->admin, $this->ownAppointment));
        $this->assertTrue($this->policy->update($this->admin, $this->otherAppointment));
    }

    public function test_admin_can_delete_any_appointment(): void
    {
        $this->assertTrue($this->policy->delete($this->admin, $this->ownAppointment));
        $this->assertTrue($this->policy->delete($this->admin, $this->otherAppointment));
    }

    public function test_admin_can_update_status_of_any_appointment(): void
    {
        $this->assertTrue($this->policy->updateStatus($this->admin, $this->ownAppointment));
        $this->assertTrue($this->policy->updateStatus($this->admin, $this->otherAppointment));
    }

    // --- Staff tests ---

    public function test_staff_can_view_any_appointments(): void
    {
        $this->assertTrue($this->policy->viewAny($this->staff));
    }

    public function test_staff_can_view_own_appointment(): void
    {
        $this->assertTrue($this->policy->view($this->staff, $this->ownAppointment));
    }

    public function test_staff_cannot_view_other_staff_appointment(): void
    {
        $this->assertFalse($this->policy->view($this->staff, $this->otherAppointment));
    }

    public function test_staff_cannot_create_appointments(): void
    {
        $this->assertFalse($this->policy->create($this->staff));
    }

    public function test_staff_cannot_update_own_appointment(): void
    {
        $this->assertFalse($this->policy->update($this->staff, $this->ownAppointment));
    }

    public function test_staff_cannot_update_other_staff_appointment(): void
    {
        $this->assertFalse($this->policy->update($this->staff, $this->otherAppointment));
    }

    public function test_staff_cannot_delete_own_appointment(): void
    {
        $this->assertFalse($this->policy->delete($this->staff, $this->ownAppointment));
    }

    public function test_staff_cannot_delete_other_staff_appointment(): void
    {
        $this->assertFalse($this->policy->delete($this->staff, $this->otherAppointment));
    }

    public function test_staff_can_update_status_of_own_appointment(): void
    {
        $this->assertTrue($this->policy->updateStatus($this->staff, $this->ownAppointment));
    }

    public function test_staff_cannot_update_status_of_other_staff_appointment(): void
    {
        $this->assertFalse($this->policy->updateStatus($this->staff, $this->otherAppointment));
    }
}
