<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\QaReplyPosted;
use App\Notifications\QaReply\QaReplyReceivedNotification;
use App\Services\ActivityNotificationService;

final class SendQaReplyNotification
{
    public function __construct(private readonly ActivityNotificationService $notifications) {}

    public function handle(QaReplyPosted $event): void
    {
        $authorId = $event->reply->thread->user_id;
        if ($authorId === $event->reply->user_id) {
            return;
        }

        $this->notifications->send([$authorId], new QaReplyReceivedNotification($event->reply));
    }
}
