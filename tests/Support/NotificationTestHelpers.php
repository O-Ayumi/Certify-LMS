<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

trait NotificationTestHelpers
{
    private function notificationFor(User $user, array $attributes = []): DatabaseNotification
    {
        return $user->notifications()->create(array_replace([
            'id' => (string) Str::uuid(),
            'type' => ChatMessageReceivedNotification::class,
            'data' => [
                'notification_type' => 'chat_message_received',
                'title' => '本人宛のお知らせ',
                'message' => '新しいメッセージがあります。',
                'url' => route('chat.show', (string) Str::ulid()),
            ],
            'read_at' => null,
        ], $attributes));
    }
}
