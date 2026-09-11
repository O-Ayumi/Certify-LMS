<?php

declare(strict_types=1);

namespace App\Notifications\QaReply;

use App\Models\QaReply;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class QaReplyReceivedNotification extends Notification
{
    public function __construct(private readonly QaReply $reply) {}

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
            'notification_type' => 'qa_reply_received',
            'title' => '質問に新しい回答があります',
            'message' => '投稿した質問に回答が届きました。',
            'url' => route('qa-board.show', $this->reply->qa_thread_id),
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
