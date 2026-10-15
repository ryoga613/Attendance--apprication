<?php

namespace App\Policies;

use App\Models\AttendanceCorrection;
use App\Models\ProposalBreakCorrection;
use App\Models\User;


class ProposalBreakCorrectionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ProposalBreakCorrection $correction): bool
    {
        return $user->admin_status || $user->id === $correction->attendanceCorrection?->user_id;
    }

     public function create(User $user, AttendanceCorrection $parent): bool
    {
        return $user->admin_status || $user->id === $parent->user_id;
    }

    public function update(User $user, ProposalBreakCorrection $correction): bool
    {
        return false;
    }

    public function delete(User $user, ProposalBreakCorrection $correction): bool
    {
        return false;
    }
}