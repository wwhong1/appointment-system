<?php

namespace Tests\Feature\Properties;

use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Policies\AppointmentPolicy;
use Faker\Factory as Faker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature: appointment-scheduling
 * Property 4: Staff access control scoped to own appointments
 *
 * For any staff member and any appointment, the system SHALL grant view and status-update
 * access if and only if the appointment's staff_id equals the requesting staff member's id,
 * and SHALL deny access otherwise.
 *
 * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
 */
class StaffAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private \Faker\Generator $faker;
    private AppointmentPolicy $policy;
    private Branch $branch;
    private Service $service;
    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->faker = Faker::create();
        $this->policy = new AppointmentPolicy();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'address' => '123 Main St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00',
            'closing_time' => '18:00',
        ]);

        $this->service = Service::create([
            'name' => 'Test Service',
            'duration_minutes' => 30,
        ]);

        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'email' => 'customer@example.com',
        ]);
    }

    private function createStaff(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ], $attributes));
    }

    private function createAdmin(): User
    {
        return User::factory()->create([
            'role' => 'admin',
        ]);
    }

    private function createAppointment(User $staff): Appointment
    {
        return Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => now()->addDays($this->faker->numberBetween(1, 30)),
            'end_datetime' => now()->addDays($this->faker->numberBetween(1, 30))->addMinutes(30),
            'status' => $this->faker->randomElement(['pending', 'confirmed', 'in-progress', 'completed', 'cancelled', 'no_show']),
        ]);
    }

    /**
     * Property 4: Staff access control scoped to own appointments
     * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
     *
     * For any staff member, the policy SHALL grant view access to appointments
     * where staff_id matches the requesting staff member's id.
     */
    public function test_property_staff_can_view_own_appointments(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $staff = $this->createStaff();
            $appointment = $this->createAppointment($staff);

            $this->assertTrue(
                $this->policy->view($staff, $appointment),
                "Iteration {$i}: Staff (ID: {$staff->id}) should be able to view their own appointment "
                . "(staff_id: {$appointment->staff_id})."
            );
        }
    }

    /**
     * Property 4: Staff access control scoped to own appointments
     * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
     *
     * For any staff member, the policy SHALL grant updateStatus access to appointments
     * where staff_id matches the requesting staff member's id.
     */
    public function test_property_staff_can_update_status_of_own_appointments(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $staff = $this->createStaff();
            $appointment = $this->createAppointment($staff);

            $this->assertTrue(
                $this->policy->updateStatus($staff, $appointment),
                "Iteration {$i}: Staff (ID: {$staff->id}) should be able to update status of their own appointment "
                . "(staff_id: {$appointment->staff_id})."
            );
        }
    }

    /**
     * Property 4: Staff access control scoped to own appointments
     * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
     *
     * For any staff member, the policy SHALL deny view access to appointments
     * where staff_id does NOT match the requesting staff member's id.
     */
    public function test_property_staff_cannot_view_other_staff_appointments(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $staff = $this->createStaff();
            $otherStaff = $this->createStaff();
            $appointment = $this->createAppointment($otherStaff);

            $this->assertFalse(
                $this->policy->view($staff, $appointment),
                "Iteration {$i}: Staff (ID: {$staff->id}) should NOT be able to view appointment "
                . "belonging to other staff (staff_id: {$appointment->staff_id})."
            );
        }
    }

    /**
     * Property 4: Staff access control scoped to own appointments
     * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
     *
     * For any staff member, the policy SHALL deny updateStatus access to appointments
     * where staff_id does NOT match the requesting staff member's id.
     */
    public function test_property_staff_cannot_update_status_of_other_staff_appointments(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $staff = $this->createStaff();
            $otherStaff = $this->createStaff();
            $appointment = $this->createAppointment($otherStaff);

            $this->assertFalse(
                $this->policy->updateStatus($staff, $appointment),
                "Iteration {$i}: Staff (ID: {$staff->id}) should NOT be able to update status of appointment "
                . "belonging to other staff (staff_id: {$appointment->staff_id})."
            );
        }
    }

    /**
     * Property 4: Staff access control scoped to own appointments
     * Validates: Requirements 3.4, 12.1, 12.2, 12.3, 12.4
     *
     * For any admin user and any appointment, the policy SHALL always grant view
     * and updateStatus access regardless of the appointment's staff_id.
     */
    public function test_property_admin_can_always_view_and_update_status_of_any_appointment(): void
    {
        $iterations = 100;

        for ($i = 0; $i < $iterations; $i++) {
            $admin = $this->createAdmin();
            $staff = $this->createStaff();
            $appointment = $this->createAppointment($staff);

            $this->assertTrue(
                $this->policy->view($admin, $appointment),
                "Iteration {$i}: Admin (ID: {$admin->id}) should always be able to view any appointment "
                . "(staff_id: {$appointment->staff_id})."
            );

            $this->assertTrue(
                $this->policy->updateStatus($admin, $appointment),
                "Iteration {$i}: Admin (ID: {$admin->id}) should always be able to update status of any appointment "
                . "(staff_id: {$appointment->staff_id})."
            );
        }
    }
}
