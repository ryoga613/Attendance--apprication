<?php

namespace App\Policies;

use App\Models\ProposalBreak;
use App\Models\User;

class ProposalBreakPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    // 管理者、または勤怠の持ち主
    public function view(User $user, ProposalBreak $break): bool
    {
        return $user->admin_status || $user->id === $break->attendance?->user_id;
    }

    // 休憩の追加・変更・削除は管理者のみ
    public function create(User $user): bool
    {
        return (bool) $user->admin_status;
    }

    public function update(User $user, ProposalBreak $break): bool
    {
        return (bool) $user->admin_status;
    }

    public function delete(User $user, ProposalBreak $break): bool
    {
        return (bool) $user->admin_status;
    }
}
