<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;

/**
 * 個人目標の認可ポリシー。
 * 目標の作成・更新・削除・達成状態変更は受講中の本人のみ許可する。
 */
class EnrollmentGoalPolicy
{
    public function create(User $user, Enrollment $enrollment): bool
    {
        return $user->role === UserRole::Student
            && $user->status === UserStatus::InProgress
            && $enrollment->user_id === $user->id
            && ! $enrollment->trashed();
    }

    public function update(User $user, EnrollmentGoal $goal): bool
    {
        return $goal->enrollment !== null && $this->create($user, $goal->enrollment);
    }

    public function delete(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }

    public function markAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }

    public function unmarkAchieved(User $user, EnrollmentGoal $goal): bool
    {
        return $this->update($user, $goal);
    }
}
