<?php

declare(strict_types=1);

namespace App\UseCases\Notification;

use Illuminate\Notifications\DatabaseNotification;

/**
 * 認可済みの通知を既読化し、保存済みの業務画面 URL を返す。
 * 本人確認は Controller から NotificationPolicy へ委譲する。
 */
final class MarkAsReadAction
{
    public function __invoke(DatabaseNotification $notification): string
    {
        // 既読日時は初回のみ更新する。チャット本体の既読には触れない。
        DatabaseNotification::query()->whereKey($notification->id)->whereNull('read_at')->update(['read_at' => now()]);

        return $notification->data['url'] ?? route('notifications.index');
    }
}
