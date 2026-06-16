<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use App\Models\Branch;
use App\Models\Service;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AppointmentService
{
    /**
     * Create a new appointment.
     *
     * Validates all business rules, calculates end_datetime from service duration,
     * sets status to pending, and persists within a transaction.
     *
     * @param array $data Must contain: branch_id, staff_id, customer_id, service_id, start_datetime
     * @return Appointment
     * @throws ValidationException
     */
    public function create(array $data): Appointment
    {
        // 1. Validate required fields
        $requiredFields = ['branch_id', 'staff_id', 'customer_id', 'service_id', 'start_datetime'];
        $missing = [];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                $missing[$field] = ["The {$field} field is required."];
            }
        }
        if (!empty($missing)) {
            throw ValidationException::withMessages($missing);
        }

        $startDatetime = $data['start_datetime'] instanceof Carbon
            ? $data['start_datetime']->utc()
            : Carbon::parse($data['start_datetime'])->utc();

        // 2. Reject if start_datetime is in the past
        if ($startDatetime->isPast()) {
            throw ValidationException::withMessages([
                'start_datetime' => ['Appointment start time must be in the future.'],
            ]);
        }

        // 3. Look up Service, calculate end_datetime
        $service = Service::findOrFail($data['service_id']);
        $endDatetime = $startDatetime->copy()->addMinutes($service->duration_minutes);

        // Look up Branch and Staff
        $branch = Branch::findOrFail($data['branch_id']);
        $staff = User::findOrFail($data['staff_id']);

        // 4. Validate operating hours
        $this->validateOperatingHours($branch, $startDatetime, $endDatetime);

        // 5. Validate staff-branch assignment
        $this->validateStaffBranch($staff, $branch);

        // 5a. Validate staff working hours (if set)
        $this->validateStaffWorkingHours($staff, $branch, $startDatetime, $endDatetime);

        // 6. Validate no overlap (within transaction for locking)
        return DB::transaction(function () use ($data, $staff, $startDatetime, $endDatetime) {
            $this->validateNoOverlap($staff, $startDatetime, $endDatetime);

            // 7. Set status to pending, 8. Persist and return
            $appointment = Appointment::create([
                'branch_id' => $data['branch_id'],
                'staff_id' => $data['staff_id'],
                'customer_id' => $data['customer_id'],
                'service_id' => $data['service_id'],
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
                'status' => AppointmentStatus::Pending->value,
            ]);

            return $appointment;
        });
    }

    /**
     * Update an existing appointment.
     *
     * Validates terminal status immutability, recalculates end_datetime if service
     * or start changed, re-validates all business rules, and persists within a transaction.
     *
     * @param Appointment $appointment The appointment to update.
     * @param array $data Fields to update.
     * @return Appointment
     * @throws ValidationException
     */
    public function update(Appointment $appointment, array $data): Appointment
    {
        // 1. Reject if appointment has terminal status
        $currentStatus = $appointment->status;
        if ($currentStatus->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ["Cannot modify an appointment with status {$currentStatus->value}."],
            ]);
        }

        // Determine effective values (use new data or fall back to existing)
        $branchId = $data['branch_id'] ?? $appointment->branch_id;
        $staffId = $data['staff_id'] ?? $appointment->staff_id;
        $customerId = $data['customer_id'] ?? $appointment->customer_id;
        $serviceId = $data['service_id'] ?? $appointment->service_id;

        $startDatetimeChanged = isset($data['start_datetime']);
        $serviceChanged = isset($data['service_id']) && (int) $data['service_id'] !== (int) $appointment->service_id;

        // Resolve start_datetime
        if ($startDatetimeChanged) {
            $startDatetime = $data['start_datetime'] instanceof Carbon
                ? $data['start_datetime']->utc()
                : Carbon::parse($data['start_datetime'])->utc();
        } else {
            $startDatetime = $appointment->start_datetime->copy()->utc();
        }

        // 3. If start_datetime changed, reject if new start is in the past
        if ($startDatetimeChanged && $startDatetime->isPast()) {
            throw ValidationException::withMessages([
                'start_datetime' => ['Appointment start time must be in the future.'],
            ]);
        }

        // 2. If start_datetime or service_id changed, recalculate end_datetime
        $service = Service::findOrFail($serviceId);
        if ($startDatetimeChanged || $serviceChanged) {
            $endDatetime = $startDatetime->copy()->addMinutes($service->duration_minutes);
        } else {
            $endDatetime = $appointment->end_datetime->copy()->utc();
        }

        // Look up Branch and Staff
        $branch = Branch::findOrFail($branchId);
        $staff = User::findOrFail($staffId);

        // 4. Re-validate operating hours, staff-branch, and overlap (excluding self)
        $this->validateOperatingHours($branch, $startDatetime, $endDatetime);
        $this->validateStaffBranch($staff, $branch);
        $this->validateStaffWorkingHours($staff, $branch, $startDatetime, $endDatetime);

        return DB::transaction(function () use ($appointment, $data, $staff, $startDatetime, $endDatetime, $branchId, $staffId, $customerId, $serviceId) {
            $this->validateNoOverlap($staff, $startDatetime, $endDatetime, $appointment->id);

            // 5. Persist and return the updated Appointment
            $appointment->update([
                'branch_id' => $branchId,
                'staff_id' => $staffId,
                'customer_id' => $customerId,
                'service_id' => $serviceId,
                'start_datetime' => $startDatetime,
                'end_datetime' => $endDatetime,
            ]);

            return $appointment->fresh();
        });
    }

    /**
     * Transition an appointment's status to a new status.
     *
     * Validates that the transition is allowed, requires cancellation reason
     * when transitioning to cancelled, and rejects transitions on terminal statuses.
     *
     * @param Appointment $appointment The appointment to transition.
     * @param AppointmentStatus $newStatus The target status.
     * @param string|null $cancellationReason Required when transitioning to cancelled.
     * @return Appointment
     * @throws ValidationException
     */
    public function transitionStatus(Appointment $appointment, AppointmentStatus $newStatus, ?string $cancellationReason = null): Appointment
    {
        $currentStatus = $appointment->status;

        // 1. Reject if current status is terminal
        if ($currentStatus->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => ["Cannot modify an appointment with status {$currentStatus->value}."],
            ]);
        }

        // 2. Check if transition is valid
        if (!$currentStatus->canTransitionTo($newStatus)) {
            throw ValidationException::withMessages([
                'status' => ["Cannot transition from {$currentStatus->value} to {$newStatus->value}."],
            ]);
        }

        // 3. If transitioning to cancelled, require cancellation_reason (1-500 chars)
        if ($newStatus === AppointmentStatus::Cancelled) {
            if ($cancellationReason === null || mb_strlen($cancellationReason) < 1 || mb_strlen($cancellationReason) > 500) {
                throw ValidationException::withMessages([
                    'cancellation_reason' => ['A cancellation reason (1-500 characters) is required.'],
                ]);
            }
        }

        // 4. Update the appointment
        $updateData = ['status' => $newStatus->value];

        if ($newStatus === AppointmentStatus::Cancelled) {
            $updateData['cancellation_reason'] = $cancellationReason;
        }

        $appointment->update($updateData);

        return $appointment->fresh();
    }

    /**
     * Validate that the appointment fits within the branch's operating hours.
     *
     * Converts start/end UTC datetimes to the branch's local timezone and checks
     * that start >= opening_time and end <= closing_time.
     *
     * @param Branch $branch
     * @param Carbon $startUtc
     * @param Carbon $endUtc
     * @throws ValidationException
     */
    public function validateOperatingHours(Branch $branch, Carbon $startUtc, Carbon $endUtc): void
    {
        $timezone = $branch->timezone;
        $startLocal = $startUtc->copy()->setTimezone($timezone);
        $endLocal   = $endUtc->copy()->setTimezone($timezone);

        // Build full datetime boundaries on the appointment's local date for correct midnight-crossing comparison
        $openingDatetime = $startLocal->copy()->setTimeFromTimeString($branch->opening_time);
        $closingDatetime = $startLocal->copy()->setTimeFromTimeString($branch->closing_time);
        // If closing is on or before opening, it means closing is next day (overnight branch — rare but safe)
        if ($closingDatetime->lte($openingDatetime)) {
            $closingDatetime->addDay();
        }

        $errors = [];

        if ($startLocal->lt($openingDatetime)) {
            $errors['start_datetime'][] = "Appointment start time is before branch opening hours ({$branch->opening_time} {$timezone}).";
        }

        if ($endLocal->gt($closingDatetime)) {
            $errors['end_datetime'][] = "Appointment end time is after branch closing hours ({$branch->closing_time} {$timezone}).";
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Validate that the staff member has no overlapping active appointments.
     *
     * Uses lockForUpdate() for race condition prevention.
     * Overlap: existing.start < new.end AND existing.end > new.start
     * Adjacent appointments (existing.end == new.start) are non-overlapping.
     *
     * @param User $staff
     * @param Carbon $startUtc
     * @param Carbon $endUtc
     * @param int|null $excludeAppointmentId Appointment ID to exclude (for updates).
     * @throws ValidationException
     */
    public function validateNoOverlap(User $staff, Carbon $startUtc, Carbon $endUtc, ?int $excludeAppointmentId = null): void
    {
        $activeStatuses = collect(AppointmentStatus::cases())
            ->filter(fn(AppointmentStatus $status) => $status->isActive())
            ->map(fn(AppointmentStatus $status) => $status->value)
            ->values()
            ->all();

        $query = Appointment::query()
            ->where('staff_id', $staff->id)
            ->whereIn('status', $activeStatuses)
            ->where('start_datetime', '<', $endUtc)
            ->where('end_datetime', '>', $startUtc)
            ->lockForUpdate();

        if ($excludeAppointmentId !== null) {
            $query->where('id', '!=', $excludeAppointmentId);
        }

        $conflicting = $query->first();

        if ($conflicting !== null) {
            $staffName = $staff->name;
            // Display times in the branch's local timezone
            $timezone = $conflicting->branch->timezone ?? 'UTC';
            $start = $conflicting->start_datetime->copy()->setTimezone($timezone)->format('Y-m-d H:i');
            $end = $conflicting->end_datetime->copy()->setTimezone($timezone)->format('Y-m-d H:i');

            throw ValidationException::withMessages([
                'start_datetime' => ["Staff member {$staffName} has a conflicting appointment from {$start} to {$end} ({$timezone})."],
            ]);
        }
    }

    /**
     * Validate that the staff member belongs to the selected branch.
     *
     * @param User $staff
     * @param Branch $branch
     * @throws ValidationException
     */
    public function validateStaffBranch(User $staff, Branch $branch): void
    {
        if ((int) $staff->branch_id !== (int) $branch->id) {
            throw ValidationException::withMessages([
                'staff_id' => ["Staff member {$staff->name} is not assigned to branch {$branch->name}."],
            ]);
        }
    }

    /**
     * Validate that the appointment fits within the staff member's working hours (if set).
     *
     * Uses the branch timezone for conversion, same as branch operating hours validation.
     * Skips validation if the staff member has no working hours configured.
     *
     * @param User $staff
     * @param Branch $branch
     * @param Carbon $startUtc
     * @param Carbon $endUtc
     * @throws ValidationException
     */
    public function validateStaffWorkingHours(User $staff, Branch $branch, Carbon $startUtc, Carbon $endUtc): void
    {
        // Skip if staff has no working hours configured
        if ($staff->working_start_time === null || $staff->working_end_time === null) {
            return;
        }

        $timezone = $branch->timezone;
        $startLocal = $startUtc->copy()->setTimezone($timezone);
        $endLocal   = $endUtc->copy()->setTimezone($timezone);

        // Build full datetime boundaries on the appointment's local date for correct midnight-crossing comparison
        $staffStartDatetime = $startLocal->copy()->setTimeFromTimeString($staff->working_start_time);
        $staffEndDatetime   = $startLocal->copy()->setTimeFromTimeString($staff->working_end_time);
        // If working end is on or before working start, end is next day
        if ($staffEndDatetime->lte($staffStartDatetime)) {
            $staffEndDatetime->addDay();
        }

        $errors = [];

        if ($startLocal->lt($staffStartDatetime)) {
            $errors['start_datetime'][] = "Appointment start time is before {$staff->name}'s working hours ({$staff->working_start_time} {$timezone}).";
        }

        if ($endLocal->gt($staffEndDatetime)) {
            $errors['end_datetime'][] = "Appointment end time is after {$staff->name}'s working hours ({$staff->working_end_time} {$timezone}).";
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }
    }
}
