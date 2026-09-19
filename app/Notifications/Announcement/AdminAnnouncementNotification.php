<?php

declare(strict_types=1);

namespace App\Notifications\Announcement;

use App\Models\AdminAnnouncement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class AdminAnnouncementNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly AdminAnnouncement $announcement,
    ) {}

    public function via($notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray($notifiable): array
    {
        return [
            'notification_type' => 'admin_announcement',
            'title' => $this->announcement->title,
            'message' => $this->announcement->body,
            'body' => $this->announcement->body,
            'url' => route('notifications.index'),
        ];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('【Certify LMS】'.$this->announcement->title)
            ->greeting('Certify LMS からのお知らせ')
            ->line($this->announcement->body)
            ->action('内容を確認する', route('notifications.index'))
            ->salutation('Certify LMS 運営チーム');
    }
}
