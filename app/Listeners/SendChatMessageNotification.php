<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\ChatMessageSent;
use App\Models\ChatMember;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Services\ActivityNotificationService;

/** ChatMessageSent は既存の送信 Action がコミット後に発火する。 */
final class SendChatMessageNotification
{
    public function __construct(private readonly ActivityNotificationService $notifications) {}

    public function handle(ChatMessageSent $event): void
    {
        $recipients = ChatMember::query()
            ->where('chat_room_id', $event->message->chat_room_id)
            ->where('user_id', '!=', $event->message->sender_user_id)
            ->pluck('user_id')->all();

        $this->notifications->send($recipients, new ChatMessageReceivedNotification($event->message));
    }
}
