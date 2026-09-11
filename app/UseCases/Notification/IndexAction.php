<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * 本人宛の通知一覧を返すユースケース。
 */
final class IndexAction
{
    public function __invoke(User $user, string $tab = 'all'): LengthAwarePaginator
    {
        $query = $tab === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        return $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();
    }
}
