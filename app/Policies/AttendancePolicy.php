<?php

namespace App\Policies;

use App\Models\Attendance;
use App\Models\User;

class AttendancePolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if (str_starts_with($ability, 'api')) {
            return null;
        }

        return $user->admin_status ? true : null;
    }

    public function store()
    {
        return true;
    }

    // AttendancePolicy に追加(管理者は before() で許可されるので、一般ユーザーだけ拒否になる)
    public function manage(User $user): bool
    {
        return false;
    }

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Attendance $attendance): bool
    {
        return $user->admin_status || $user->id === $attendance->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Attendance $attendance): bool
    {
        return false;
    }

    public function requestCorrection(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id
            && ! $attendance->attendanceCorrections()
                ->where('approval_status', '承認待ち')
                ->exists();
    }

    public function delete(User $user, Attendance $attendance): bool
    {
        return false;
    }

    public function viewReport(User $user): bool
    {
        return true;

    }

    // API
    public function apiUpdate(User $user, Attendance $attendance)
    {
        return $user->id === $attendance->user_id;
    }

    public function apiDelete(User $user, Attendance $attendance): bool
    {
        return $user->id === $attendance->user_id;
    }
}
