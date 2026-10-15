<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function before(User $user): ?bool
    {
        return $user->admin_status ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    public function create(User $user): bool
    {

        return true;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return false;
    }

    // 修正申請は、自分の勤怠だけ
    public function requestCorrection(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return false;
    }
}
