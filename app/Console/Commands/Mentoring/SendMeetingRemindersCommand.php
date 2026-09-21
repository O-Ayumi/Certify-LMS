<?php

declare(strict_types=1);

namespace App\Console\Commands\Mentoring;

use App\Enums\MeetingReminderWindow;
use App\Enums\MeetingStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReminderNotification;
use App\Services\ActivityNotificationService;
use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

class SendMeetingRemindersCommand extends Command
{
    protected $signature = 'notifications:send-meeting-reminders {--window=eve}';

    protected $description = '予約済み面談のリマインダー通知を送信する';

    public function handle(ActivityNotificationService $notifications): int
    {
        $window = MeetingReminderWindow::tryFrom((string) $this->option('window'));

        if ($window === null) {
            $this->error('window は eve または one_hour_before を指定してください。');

            return self::INVALID;
        }

        [$from, $to] = $this->timeRange($window);
        $sent = 0;
        $skipped = 0;

        Meeting::query()
            ->where('status', MeetingStatus::Reserved->value)
            ->whereBetween('scheduled_at', [$from, $to])
            ->orderBy('id')
            ->chunkById(100, function ($meetings) use ($notifications, $window, &$sent, &$skipped): void {
                foreach ($meetings as $meeting) {
                    $notification = new MeetingReminderNotification($meeting, $window);
                    $recipients = array_unique([$meeting->student_id, $meeting->coach_id]);

                    foreach ($recipients as $recipientId) {
                        if ($this->alreadySent($recipientId, $meeting->id, $window)) {
                            $skipped++;

                            continue;
                        }

                        $notifications->send([$recipientId], $notification);
                        $sent++;
                    }
                }
            });

        $this->info("リマインダーを {$sent} 件送信しました。重複スキップ: {$skipped} 件。");

        return self::SUCCESS;
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private function timeRange(MeetingReminderWindow $window): array
    {
        $now = now();

        return match ($window) {
            MeetingReminderWindow::Eve => [
                $now->copy()->addDay()->startOfDay(),
                $now->copy()->addDay()->endOfDay(),
            ],
            MeetingReminderWindow::OneHourBefore => [
                $now->copy()->addMinutes(55),
                $now->copy()->addMinutes(65),
            ],
        };
    }

    private function alreadySent(string $recipientId, string $meetingId, MeetingReminderWindow $window): bool
    {
        return DatabaseNotification::query()
            ->where('notifiable_type', User::class)
            ->where('notifiable_id', $recipientId)
            ->where('type', MeetingReminderNotification::class)
            ->whereJsonContains('data->meeting_id', $meetingId)
            ->whereJsonContains('data->window', $window->value)
            ->exists();
    }
}
