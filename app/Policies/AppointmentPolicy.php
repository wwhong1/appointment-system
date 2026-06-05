<?php

namespace App\Policies;

use App\Models\Appointment;
use App\Models\User;

class AppointmentPolicy
{
    /**
     * Determine whether the user can view any appointments.
     * Admin: full access. Staff: allowed (query scoping handled in resource).
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the appointment.
     * Admin: full access. Staff: only own appointments.
     */
    public function view(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $appointment->staff_id === $user->id;
    }

    /**
     * Determine whether the user can create appointments.
     * Admin: allowed. Staff: denied.
     */
    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the appointment.
     * Admin: allowed. Staff: denied (staff can only update status via updateStatus).
     */
    public function update(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can delete the appointment.
     * Admin: allowed. Staff: denied.
     */
    public function delete(User $user, Appointment $appointment): bool
    {
        return $user->isAdmin();
    }

    /**
     * Determine whether the user can update the appointment status.
     * Admin: allowed. Staff: only own appointments.
     */
    public function updateStatus(User $user, Appointment $appointment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $appointment->staff_id === $user->id;
    }
}
