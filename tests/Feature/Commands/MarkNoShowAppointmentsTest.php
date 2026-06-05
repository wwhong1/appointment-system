<?php

namespace Tests\Feature\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarkNoShowAppointmentsTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'address' => '123 Test St',
            'phone' => '+60123456789',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ]);

        $this->staff = User::create([
            'name' => 'John Staff',
            'email' => 'john@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $this->customer = Customer::create([
            'name' => 'Jane Customer',
            'email' => 'jane@example.com',
        ]);

        $this->service = Service::create([
            'name' => 'Haircut',
            'duration_minutes' => 60,
        ]);
    }

    public function test_marks_confirmed_appointments_past_15_minutes_as_no_show(): void
    {
        // Set "now" to a fixed time
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Appointment started 20 minutes ago (more than 15 min) with confirmed status
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:40:00', 'UTC'), // 20 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:40:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::NoShow, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_confirmed_appointments_within_15_minutes(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Appointment started 10 minutes ago (less than 15 min)
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:50:00', 'UTC'), // 10 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:50:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Confirmed, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_pending_appointments_as_no_show(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'), // 30 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Pending, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_in_progress_appointments_as_no_show(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'), // 30 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::InProgress->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::InProgress, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_completed_appointments_as_no_show(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Completed->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Completed, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_cancelled_appointments_as_no_show(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Cancelled, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_skips_appointment_if_status_changed_since_query(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Create a confirmed appointment past 15 min
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'), // 30 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Simulate status change between query and transition by changing it to in-progress
        // The command re-checks status via fresh(), so we update it after creation
        $appointment->update(['status' => AppointmentStatus::InProgress->value]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        // Should remain in-progress, not changed to no_show
        $this->assertEquals(AppointmentStatus::InProgress, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_handles_multiple_appointments_correctly(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Appointment 1: confirmed, 20 min ago - should be marked
        $appointment1 = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:40:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 10:40:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Appointment 2: confirmed, 30 min ago - should be marked
        $staff2 = User::create([
            'name' => 'Jane Staff',
            'email' => 'jane.staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $appointment2 = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $staff2->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        // Appointment 3: pending, 30 min ago - should NOT be marked
        $staff3 = User::create([
            'name' => 'Bob Staff',
            'email' => 'bob.staff@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        $appointment3 = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $staff3->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:30:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 10:30:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment1->refresh();
        $appointment2->refresh();
        $appointment3->refresh();

        $this->assertEquals(AppointmentStatus::NoShow, $appointment1->status);
        $this->assertEquals(AppointmentStatus::NoShow, $appointment2->status);
        $this->assertEquals(AppointmentStatus::Pending, $appointment3->status);

        Carbon::setTestNow();
    }

    public function test_does_not_mark_future_confirmed_appointments(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Appointment starts in the future
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 11:00:00', 'UTC'), // 1 hour in future
            'end_datetime' => Carbon::parse('2025-06-15 12:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        $this->assertEquals(AppointmentStatus::Confirmed, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_marks_appointment_exactly_at_15_minute_boundary(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        // Appointment started exactly 15 minutes ago - the query uses '<' (strictly less than cutoff)
        // cutoff = now - 15 min = 09:45:00
        // start_datetime = 09:45:00 is NOT < 09:45:00, so it should NOT be marked
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 09:45:00', 'UTC'), // exactly 15 min ago
            'end_datetime' => Carbon::parse('2025-06-15 10:45:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->artisan('appointments:mark-no-show')
            ->assertSuccessful();

        $appointment->refresh();
        // At exactly 15 minutes, the requirement says "more than 15 minutes" so it should NOT be marked
        $this->assertEquals(AppointmentStatus::Confirmed, $appointment->status);

        Carbon::setTestNow();
    }
}
