<?php

declare(strict_types=1);

namespace App\Notifications\Chat;

use App\Models\ChatMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

final class ChatMessageReceivedNotification extends Notification
{
    public function __construct(private readonly ChatMessage $message) {}

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
            'notification_type' => 'chat_message_received',
            'title' => 'チャットに新しいメッセージがあります',
            'message' => '参加中のチャットにメッセージが届きました。',
            'url' => route('chat.show', $this->message->chat_room_id),
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
