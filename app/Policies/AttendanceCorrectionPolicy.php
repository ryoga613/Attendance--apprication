<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\User;


class AttendanceCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    // 申請の詳細を見られるか: 管理者、または申請した本人
    public function view(User $user, AttendanceCorrection $correction): bool
    {
        return $user->admin_status || $user->id === $correction->user_id;
    }

    // 申請を作れるか(ログインしていればOK。自分の勤怠かどうかは AttendancePolicy で見る)
    public function create(User $user): bool
    {
        return true;
    }

    // 承認できるか: 管理者のみ、かつ「承認待ち」のものだけ
    public function approve(User $user, AttendanceCorrection $correction): bool
    {
        return $user->admin_status ;
    }

    public function update(User $user, AttendanceCorrection $correction): bool
    {
        return false;
    }

    public function delete(User $user, AttendanceCorrection $correction): bool
    {
        return false;
    }
}