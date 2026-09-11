<?php

declare(strict_types=1);

namespace App\Notifications\Meeting;

use App\Models\Meeting;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class MeetingReservedNotification extends Notification
{
    public function __construct(private readonly Meeting $meeting) {}

    /**
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    /**
     * @return array{notification_type: string, title: string, message: string, url: string}
     */
    public function toArray($notifiable): array
    {
        return [
            'notification_type' => 'meeting_reserved',
            'title' => '面談が予約されました',
            'message' => $this->meeting->scheduled_at->format('Y/m/d H:i').'の面談が予約されました。',
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
