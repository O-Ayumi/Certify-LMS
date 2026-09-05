<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\CertificationStatus;
use App\Enums\UserRole;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;

class QaReplyPolicy
{
    public function create(User $user, QaThread $thread): bool
    {
        $thread->loadMissing('certification');
        if ($thread->certification->status !== CertificationStatus::Published) {
            return false;
        }

        return match ($user->role) {
            UserRole::Student => true,
            UserRole::Coach => $thread->certification->coaches()->whereKey($user->id)->exists(),
            UserRole::Admin => false,
        };
    }

    public function update(User $user, QaReply $reply): bool
    {
        $reply->loadMissing('thread.certification');

        return $reply->user_id === $user->id
            && $reply->thread->certification->status === CertificationStatus::Published
            && ($user->role === UserRole::Student
                || ($user->role === UserRole::Coach && $reply->thread->certification->coaches()->whereKey($user->id)->exists()));
    }

    public function delete(User $user, QaReply $reply): bool
    {
        return $user->role === UserRole::Admin || $this->update($user, $reply);
    }
}
