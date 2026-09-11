<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Events\MeetingReserved;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Services\ActivityNotificationService;

final class SendMeetingNotification
{
    public function __construct(private readonly ActivityNotificationService $notifications) {}

    public function handle(MeetingReserved|MeetingCanceled $event): void
    {
        $meeting = $event->meeting;

        if ($event instanceof MeetingReserved) {
            $this->notifications->send([$meeting->coach_id], new MeetingReservedNotification($meeting));

            return;
        }

        $recipients = array_values(array_diff(
            [$meeting->student_id, $meeting->coach_id],
            [$meeting->canceled_by_user_id],
        ));
        $this->notifications->send($recipients, new MeetingCanceledNotification($meeting));
    }
}
