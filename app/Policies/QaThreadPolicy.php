<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;

class QaThreadPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, QaThread $thread): bool
    {
        $thread->loadMissing('certification');

        return match ($user->role) {
            UserRole::Admin => true,
            UserRole::Student => $thread->certification->status === CertificationStatus::Published,
            UserRole::Coach => $thread->certification->status === CertificationStatus::Published
                && $thread->certification->coaches()->whereKey($user->id)->exists(),
        };
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Student;
    }

    public function update(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Student && $thread->user_id === $user->id;
    }

    public function delete(User $user, QaThread $thread): bool
    {
        return $user->role === UserRole::Admin
            || ($user->role === UserRole::Student && $thread->user_id === $user->id && ! $thread->replies()->exists());
    }

    public function resolve(User $user, QaThread $thread): bool
    {
        return $this->update($user, $thread);
    }

    public function unresolve(User $user, QaThread $thread): bool
    {
        return $this->update($user, $thread);
    }
}
