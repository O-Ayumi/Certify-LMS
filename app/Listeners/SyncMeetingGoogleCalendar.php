<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\MeetingCanceled;
use App\Events\MeetingReserved;
use App\Services\GoogleCalendarService;

final class SyncMeetingGoogleCalendar
{
    public function __construct(private readonly GoogleCalendarService $google) {}

    public function handle(MeetingReserved|MeetingCanceled $event): void
    {
        $meeting = $event->meeting->loadMissing('coach.googleCredential');
        $credential = $meeting->coach?->googleCredential;
        if (! $credential) {
            return;
        }
        if ($event instanceof MeetingReserved) {
            if ($id = $this->google->createEvent($credential, $meeting)) {
                $meeting->update(['google_event_id' => $id]);
            }

            return;
        }
        $this->google->deleteEvent($credential, $meeting);
    }
}
