<?php

namespace Tests\Feature\Rules;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Service;
use App\Models\User;
use App\Rules\NoOverlapRule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NoOverlapRuleTest extends TestCase
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

    private function createAppointment(
        string $start,
        string $end,
        AppointmentStatus $status = AppointmentStatus::Confirmed,
        ?User $staff = null,
    ): Appointment {
        return Appointment::create([
            'branch_id' => $this->branch->id,
            'staff_id' => ($staff ?? $this->staff)->id,
            'customer_id' => $this->customer->id,
            'service_id' => $this->service->id,
            'start_datetime' => Carbon::parse($start, 'UTC'),
            'end_datetime' => Carbon::parse($end, 'UTC'),
            'status' => $status->value,
        ]);
    }

    private function runRule(NoOverlapRule $rule): array
    {
        $errors = [];
        $fail = function (string $message) use (&$errors) {
            $errors[] = $message;
        };

        $rule->validate('start_datetime', null, $fail);

        return $errors;
    }

    public function test_passes_when_no_existing_appointments(): void
    {
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_new_appointment_is_before_existing(): void
    {
        // Existing: 12:00-13:00
        $this->createAppointment('2025-01-15 12:00:00', '2025-01-15 13:00:00');

        // New: 10:00-11:00 (completely before)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_new_appointment_is_after_existing(): void
    {
        // Existing: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        // New: 12:00-13:00 (completely after)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 12:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 13:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_adjacent_end_equals_start(): void
    {
        // Existing: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        // New: 11:00-12:00 (adjacent, not overlapping)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 12:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_adjacent_start_equals_end(): void
    {
        // Existing: 11:00-12:00
        $this->createAppointment('2025-01-15 11:00:00', '2025-01-15 12:00:00');

        // New: 10:00-11:00 (adjacent, not overlapping)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_fails_when_new_appointment_overlaps_existing(): void
    {
        // Existing: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        // New: 10:30-11:30 (overlaps)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('John Staff', $errors[0]);
        $this->assertStringContainsString('conflicting appointment', $errors[0]);
    }

    public function test_fails_when_new_appointment_is_contained_within_existing(): void
    {
        // Existing: 10:00-13:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 13:00:00');

        // New: 11:00-12:00 (fully inside existing)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 12:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('conflicting appointment', $errors[0]);
    }

    public function test_fails_when_new_appointment_contains_existing(): void
    {
        // Existing: 11:00-12:00
        $this->createAppointment('2025-01-15 11:00:00', '2025-01-15 12:00:00');

        // New: 10:00-13:00 (fully contains existing)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 13:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('conflicting appointment', $errors[0]);
    }

    public function test_passes_when_overlap_is_with_cancelled_appointment(): void
    {
        // Existing cancelled: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::Cancelled);

        // New: 10:30-11:30 (would overlap, but existing is cancelled)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_passes_when_overlap_is_with_no_show_appointment(): void
    {
        // Existing no_show: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::NoShow);

        // New: 10:30-11:30 (would overlap, but existing is no_show)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_fails_with_pending_status_appointment(): void
    {
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::Pending);

        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
    }

    public function test_fails_with_in_progress_status_appointment(): void
    {
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::InProgress);

        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
    }

    public function test_fails_with_completed_status_appointment(): void
    {
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::Completed);

        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
    }

    public function test_passes_when_excluding_self_on_update(): void
    {
        // Existing: 10:00-11:00
        $existing = $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        // Update same appointment to 10:30-11:30 (exclude self)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
            excludeAppointmentId: $existing->id,
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_fails_when_excluding_self_but_overlaps_another(): void
    {
        // Existing appointment being updated: 10:00-11:00
        $existing = $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        // Another existing appointment: 12:00-13:00
        $this->createAppointment('2025-01-15 12:00:00', '2025-01-15 13:00:00');

        // Update first appointment to 12:30-13:30 (overlaps second)
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 12:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 13:30:00', 'UTC'),
            excludeAppointmentId: $existing->id,
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
    }

    public function test_passes_when_different_staff_has_overlapping_appointment(): void
    {
        $otherStaff = User::create([
            'name' => 'Other Staff',
            'email' => 'other@example.com',
            'password' => 'password123',
            'role' => 'staff',
            'branch_id' => $this->branch->id,
        ]);

        // Existing for other staff: 10:00-11:00
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00', AppointmentStatus::Confirmed, $otherStaff);

        // New for our staff at same time: should pass
        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:00:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:00:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertEmpty($errors);
    }

    public function test_error_message_contains_staff_name_and_times(): void
    {
        $this->createAppointment('2025-01-15 10:00:00', '2025-01-15 11:00:00');

        $rule = new NoOverlapRule(
            staffId: $this->staff->id,
            startDatetime: Carbon::parse('2025-01-15 10:30:00', 'UTC'),
            endDatetime: Carbon::parse('2025-01-15 11:30:00', 'UTC'),
        );

        $errors = $this->runRule($rule);

        $this->assertCount(1, $errors);
        $this->assertStringContainsString('John Staff', $errors[0]);
        $this->assertStringContainsString('2025-01-15 10:00', $errors[0]);
        $this->assertStringContainsString('2025-01-15 11:00', $errors[0]);
    }
}
