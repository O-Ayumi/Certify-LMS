<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Jobs\SendDatabaseNotification;
use App\Jobs\SendNotificationMail;
use App\Models\User;
use Illuminate\Notifications\Notification;

/** 各業務イベントの当事者から配信対象を絞り、通知とメールを別ジョブで送る。 */
final class ActivityNotificationService
{
    /** @param array<int, string> $recipientIds */
    public function send(array $recipientIds, Notification $notification): void
    {
        // 配信時点の状態を参照する。SoftDeletes により削除済みユーザーも除外される。
        $recipients = User::query()
            ->whereKey($recipientIds)
            ->where('status', UserStatus::InProgress)
            ->whereIn('role', [UserRole::Student->value, UserRole::Coach->value])
            ->get();

        foreach ($recipients as $recipient) {
            SendDatabaseNotification::dispatch($recipient->id, $notification)->afterCommit();
            SendNotificationMail::dispatch($recipient->id, $notification)->afterCommit();
        }
    }
}
