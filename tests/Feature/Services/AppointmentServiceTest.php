<?php

namespace Tests\Feature\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Services\AppointmentService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class AppointmentServiceTest extends TestCase
{
    use RefreshDatabase;

    private Branch $branch;
    private User $staff;
    private Customer $customer;
    private Service $service;
    private AppointmentService $appointmentService;

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

        $this->appointmentService = new AppointmentService();
    }

    // ==========================================
    // CREATE TESTS
    // ==========================================

    public function test_create_successfully_creates_appointment(): void
    {
        // 09:00 KL = 01:00 UTC (UTC+8)
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'), // 09:00 KL
        ];

        $appointment = $this->appointmentService->create($data);

        $this->assertInstanceOf(Appointment::class, $appointment);
        $this->assertEquals($this->branch->id, $appointment->branch_id);
        $this->assertEquals($this->staff->id, $appointment->staff_id);
        $this->assertEquals($this->customer->id, $appointment->customer_id);
        $this->assertEquals($this->service->id, $appointment->service_id);
        $this->assertEquals(AppointmentStatus::Pending, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_create_calculates_end_datetime_from_service_duration(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $start = Carbon::parse('2025-06-15 01:00:00', 'UTC'); // 09:00 KL

        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => $start,
        ];

        $appointment = $this->appointmentService->create($data);

        // Service is 60 minutes, so end = start + 60 min
        $expectedEnd = $start->copy()->addMinutes(60);
        $this->assertEquals($expectedEnd->toDateTimeString(), $appointment->end_datetime->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_create_sets_status_to_pending(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
        ];

        $appointment = $this->appointmentService->create($data);

        $this->assertEquals(AppointmentStatus::Pending, $appointment->status);

        Carbon::setTestNow();
    }

    public function test_create_rejects_missing_required_fields(): void
    {
        $this->expectException(ValidationException::class);

        $this->appointmentService->create([]);
    }

    public function test_create_rejects_past_start_datetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $this->expectException(ValidationException::class);

        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'), // in the past
        ];

        try {
            $this->appointmentService->create($data);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_create_rejects_outside_operating_hours(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $this->expectException(ValidationException::class);

        // 08:00 KL = 00:00 UTC — before opening (09:00 KL)
        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 00:00:00', 'UTC'),
        ];

        try {
            $this->appointmentService->create($data);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_create_rejects_staff_not_at_branch(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'address' => '456 Other St',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ]);

        $this->expectException(ValidationException::class);

        // Staff belongs to $this->branch, not $otherBranch
        $data = [
            'branch_id' => $otherBranch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
        ];

        try {
            $this->appointmentService->create($data);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_create_rejects_overlapping_appointment(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        // Create an existing appointment: 09:00-10:00 KL (01:00-02:00 UTC)
        Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->expectException(ValidationException::class);

        // Try to create overlapping: 09:30-10:30 KL (01:30-02:30 UTC)
        $data = [
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:30:00', 'UTC'),
        ];

        try {
            $this->appointmentService->create($data);
        } finally {
            Carbon::setTestNow();
        }
    }

    // ==========================================
    // UPDATE TESTS
    // ==========================================

    public function test_update_successfully_updates_appointment(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $newStart = Carbon::parse('2025-06-15 03:00:00', 'UTC'); // 11:00 KL
        $updated = $this->appointmentService->update($appointment, [
            'start_datetime' => $newStart,
        ]);

        $this->assertEquals($newStart->toDateTimeString(), $updated->start_datetime->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_update_recalculates_end_datetime_when_start_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $newStart = Carbon::parse('2025-06-15 03:00:00', 'UTC'); // 11:00 KL
        $updated = $this->appointmentService->update($appointment, [
            'start_datetime' => $newStart,
        ]);

        // Service is 60 min, so end = 03:00 + 60 = 04:00 UTC
        $expectedEnd = $newStart->copy()->addMinutes(60);
        $this->assertEquals($expectedEnd->toDateTimeString(), $updated->end_datetime->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_update_recalculates_end_datetime_when_service_changes(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $longerService = Service::create([
            'name' => 'Long Service',
            'duration_minutes' => 120,
        ]);

        $updated = $this->appointmentService->update($appointment, [
            'service_id' => $longerService->id,
        ]);

        // Start stays at 01:00, new service is 120 min, so end = 03:00 UTC
        $expectedEnd = Carbon::parse('2025-06-15 03:00:00', 'UTC');
        $this->assertEquals($expectedEnd->toDateTimeString(), $updated->end_datetime->toDateTimeString());

        Carbon::setTestNow();
    }

    public function test_update_rejects_terminal_status_completed(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Completed->value,
        ]);

        $this->expectException(ValidationException::class);

        $this->appointmentService->update($appointment, [
            'start_datetime' => Carbon::parse('2025-06-16 01:00:00', 'UTC'),
        ]);
    }

    public function test_update_rejects_terminal_status_cancelled(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled->value,
        ]);

        $this->expectException(ValidationException::class);

        $this->appointmentService->update($appointment, [
            'start_datetime' => Carbon::parse('2025-06-16 01:00:00', 'UTC'),
        ]);
    }

    public function test_update_rejects_terminal_status_no_show(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::NoShow->value,
        ]);

        $this->expectException(ValidationException::class);

        $this->appointmentService->update($appointment, [
            'start_datetime' => Carbon::parse('2025-06-16 01:00:00', 'UTC'),
        ]);
    }

    public function test_update_rejects_past_start_datetime(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-15 10:00:00', 'UTC'));

        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 12:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 13:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $this->expectException(ValidationException::class);

        try {
            $this->appointmentService->update($appointment, [
                'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'), // in the past
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_update_rejects_overlap_excluding_self(): void
    {
        Carbon::setTestNow(Carbon::parse('2025-06-01 00:00:00', 'UTC'));

        // Existing appointment 1: 09:00-10:00 KL (01:00-02:00 UTC)
        $appointment1 = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        // Existing appointment 2: 11:00-12:00 KL (03:00-04:00 UTC)
        Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 03:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 04:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->expectException(ValidationException::class);

        // Try to move appointment1 to overlap with appointment2
        try {
            $this->appointmentService->update($appointment1, [
                'start_datetime' => Carbon::parse('2025-06-15 03:30:00', 'UTC'),
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    // ==========================================
    // VALIDATION HELPER TESTS
    // ==========================================

    public function test_validate_operating_hours_passes_within_hours(): void
    {
        // 09:00-10:00 KL = 01:00-02:00 UTC (within 09:00-17:00 KL)
        $start = Carbon::parse('2025-06-15 01:00:00', 'UTC');
        $end = Carbon::parse('2025-06-15 02:00:00', 'UTC');

        // Should not throw
        $this->appointmentService->validateOperatingHours($this->branch, $start, $end);
        $this->assertTrue(true);
    }

    public function test_validate_operating_hours_rejects_before_opening(): void
    {
        // 08:00-09:00 KL = 00:00-01:00 UTC (before 09:00 KL opening)
        $start = Carbon::parse('2025-06-15 00:00:00', 'UTC');
        $end = Carbon::parse('2025-06-15 01:00:00', 'UTC');

        $this->expectException(ValidationException::class);
        $this->appointmentService->validateOperatingHours($this->branch, $start, $end);
    }

    public function test_validate_operating_hours_rejects_after_closing(): void
    {
        // 16:00-18:00 KL = 08:00-10:00 UTC (end after 17:00 KL closing)
        $start = Carbon::parse('2025-06-15 08:00:00', 'UTC');
        $end = Carbon::parse('2025-06-15 10:00:00', 'UTC');

        $this->expectException(ValidationException::class);
        $this->appointmentService->validateOperatingHours($this->branch, $start, $end);
    }

    public function test_validate_staff_branch_passes_when_matched(): void
    {
        // Should not throw
        $this->appointmentService->validateStaffBranch($this->staff, $this->branch);
        $this->assertTrue(true);
    }

    public function test_validate_staff_branch_rejects_when_mismatched(): void
    {
        $otherBranch = Branch::create([
            'name' => 'Other Branch',
            'address' => '456 Other St',
            'phone' => '+60198765432',
            'timezone' => 'Asia/Kuala_Lumpur',
            'opening_time' => '09:00:00',
            'closing_time' => '17:00:00',
        ]);

        $this->expectException(ValidationException::class);
        $this->appointmentService->validateStaffBranch($this->staff, $otherBranch);
    }

    public function test_validate_no_overlap_passes_when_no_conflicts(): void
    {
        $start = Carbon::parse('2025-06-15 01:00:00', 'UTC');
        $end = Carbon::parse('2025-06-15 02:00:00', 'UTC');

        // Should not throw
        $this->appointmentService->validateNoOverlap($this->staff, $start, $end);
        $this->assertTrue(true);
    }

    public function test_validate_no_overlap_rejects_when_conflict_exists(): void
    {
        Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->expectException(ValidationException::class);
        $this->appointmentService->validateNoOverlap(
            $this->staff,
            Carbon::parse('2025-06-15 01:30:00', 'UTC'),
            Carbon::parse('2025-06-15 02:30:00', 'UTC'),
        );
    }

    // ==========================================
    // TRANSITION STATUS TESTS
    // ==========================================

    public function test_transition_status_pending_to_confirmed(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $updated = $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Confirmed);

        $this->assertEquals(AppointmentStatus::Confirmed, $updated->status);
    }

    public function test_transition_status_pending_to_cancelled_with_reason(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $updated = $this->appointmentService->transitionStatus(
            $appointment,
            AppointmentStatus::Cancelled,
            'Customer requested cancellation'
        );

        $this->assertEquals(AppointmentStatus::Cancelled, $updated->status);
        $this->assertEquals('Customer requested cancellation', $updated->cancellation_reason);
    }

    public function test_transition_status_confirmed_to_in_progress(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $updated = $this->appointmentService->transitionStatus($appointment, AppointmentStatus::InProgress);

        $this->assertEquals(AppointmentStatus::InProgress, $updated->status);
    }

    public function test_transition_status_in_progress_to_completed(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::InProgress->value,
        ]);

        $updated = $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Completed);

        $this->assertEquals(AppointmentStatus::Completed, $updated->status);
    }

    public function test_transition_status_rejects_terminal_status_completed(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Completed->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Pending);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
            $this->assertStringContainsString('Cannot modify an appointment with status completed', $e->errors()['status'][0]);
        }
    }

    public function test_transition_status_rejects_terminal_status_cancelled(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Cancelled->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Pending);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
            $this->assertStringContainsString('Cannot modify an appointment with status cancelled', $e->errors()['status'][0]);
        }
    }

    public function test_transition_status_rejects_terminal_status_no_show(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::NoShow->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Pending);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
            $this->assertStringContainsString('Cannot modify an appointment with status no_show', $e->errors()['status'][0]);
        }
    }

    public function test_transition_status_rejects_invalid_transition(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Completed);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('status', $e->errors());
            $this->assertStringContainsString('Cannot transition from pending to completed', $e->errors()['status'][0]);
        }
    }

    public function test_transition_status_rejects_cancellation_without_reason(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Cancelled);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancellation_reason', $e->errors());
            $this->assertStringContainsString('A cancellation reason (1-500 characters) is required', $e->errors()['cancellation_reason'][0]);
        }
    }

    public function test_transition_status_rejects_cancellation_with_empty_reason(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Cancelled, '');
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancellation_reason', $e->errors());
        }
    }

    public function test_transition_status_rejects_cancellation_with_reason_over_500_chars(): void
    {
        $appointment = Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => $this->staff->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse('2025-06-15 01:00:00', 'UTC'),
            'end_datetime' => Carbon::parse('2025-06-15 02:00:00', 'UTC'),
            'status' => AppointmentStatus::Pending->value,
        ]);

        $longReason = str_repeat('a', 501);

        try {
            $this->appointmentService->transitionStatus($appointment, AppointmentStatus::Cancelled, $longReason);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('cancellation_reason', $e->errors());
        }
    }
}
