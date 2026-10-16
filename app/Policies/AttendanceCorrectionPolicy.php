<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;

class AttendanceCorrectionPolicy
{
    public function before(User $user): ?bool
    {
        return $user->admin_status ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, AttendanceCorrection $application): bool
    {
        return $user->admin_status || $user->id === $application->user_id || $user->id === $application->attendanceRecord->user_id;
    }

    public function approve(User $user, AttendanceCorrection $application): bool
    {
        return false;
    }

    public function store()
    {
        return true;
    }

    public function update(User $user, AttendanceCorrection $application): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceCorrection $application): bool
    {
        return false;
    }
}
