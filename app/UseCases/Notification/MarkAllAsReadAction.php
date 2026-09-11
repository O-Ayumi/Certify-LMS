<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;

/** ページや絞り込みによらず、本人の未読通知をすべて既読化する。 */
final class MarkAllAsReadAction
{
    public function __invoke(User $user): void
    {
        $user->unreadNotifications()->update(['read_at' => now()]);
    }
}
