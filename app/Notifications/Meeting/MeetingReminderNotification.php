<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Enums\MeetingReminderWindow;
use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MeetingReminderNotification extends Notification
{
    public function __construct(
        private readonly Meeting $meeting,
        private readonly MeetingReminderWindow $window,
    ) {}

    /** @return array<int, string> */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /** @return array{notification_type: string, meeting_id: string, window: string, title: string, message: string, url: string} */
    public function toArray($notifiable): array
    {
        $title = match ($this->window) {
            MeetingReminderWindow::Eve => '明日の面談のお知らせ',
            MeetingReminderWindow::OneHourBefore => '1時間後の面談のお知らせ',
        };

        return [
            'notification_type' => 'meeting_reminder',
            'meeting_id' => $this->meeting->id,
            'window' => $this->window->value,
            'title' => $title,
            'message' => $this->meeting->scheduled_at->format('Y/m/d H:i').'に面談があります。',
            'url' => route('meetings.show', $this->meeting->id),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        $data = $this->toArray($notifiable);

        return (new MailMessage)
            ->subject('【Certify LMS】'.$data['title'])
            ->greeting('Certify LMS からのお知らせ')
            ->line($data['message'])
            ->action('内容を確認する', $data['url'])
            ->salutation('Certify LMS 運営チーム');
    }
}
