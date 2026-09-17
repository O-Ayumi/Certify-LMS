<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/** 受講登録メモの閲覧・追加・編集・削除を認可するPolicy。 */
class EnrollmentNotePolicy
{
    public function viewAny(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccess($user, $enrollment);
    }

    public function create(User $user, Enrollment $enrollment): bool
    {
        return $this->canAccess($user, $enrollment);
    }

    public function update(User $user, EnrollmentNote $note): bool
    {
        return $this->canAccess($user, $note->enrollment) &&
            ($user->role === UserRole::Admin || $note->author_user_id === $user->id);
    }

    public function delete(User $user, EnrollmentNote $note): bool
    {
        return $this->update($user, $note);
    }

    private function canAccess(User $user, ?Enrollment $enrollment): bool
    {
        if ($enrollment === null || $enrollment->trashed()) {
            return false;
        }

        return $user->role === UserRole::Admin ||
            ($user->role === UserRole::Coach &&
                $enrollment->certification?->coaches()->whereKey($user->id)->exists());
    }
}
