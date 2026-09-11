<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

/** 各業務イベントの当事者から配信対象を絞り、DB 保存後に同期でメールを送る。 */
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
            $recipient->notifyNow($notification, ['database']);

            try {
                $recipient->notifyNow($notification, ['mail']);
            } catch (Throwable $exception) {
                // 失敗した宛先のメールにより、確定済み業務・DB 通知・他の宛先への配信を妨げない。
                Log::error('業務通知のメール送信に失敗しました。', [
                    'user_id' => $recipient->id,
                    'notification_type' => $notification::class,
                    'exception' => $exception,
                ]);
            }
        }
    }
}
