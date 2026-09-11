<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Notifications\DatabaseNotification;

/** 通知の閲覧・既読化は受講状態によらず本人に許可する。 */
class NotificationPolicy
{
    public function viewAny(User $user): bool
    {
        return in_array($user->role, [UserRole::Student, UserRole::Coach, UserRole::Admin], true);
    }

    public function update(User $user, DatabaseNotification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass()
            && $notification->notifiable_id === $user->id;
    }
}
