<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Notifications\QaReply\QaReplyReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_four_types_have_matching_database_and_mail_destinations_without_full_body(): void
    {
        $user = User::factory()->create();
        $message = ChatMessage::factory()->create(['body' => 'メールに転載しないチャット本文']);
        $reply = QaReply::factory()->create(['body' => 'メールに転載しない回答本文']);
        $meeting = Meeting::factory()->create();
        $cases = [
            [new ChatMessageReceivedNotification($message), 'chat_message_received', route('chat.show', $message->chat_room_id)],
            [new QaReplyReceivedNotification($reply), 'qa_reply_received', route('qa-board.show', $reply->qa_thread_id)],
            [new MeetingReservedNotification($meeting), 'meeting_reserved', route('meetings.show', $meeting)],
            [new MeetingCanceledNotification($meeting), 'meeting_canceled', route('meetings.show', $meeting)],
        ];
        foreach ($cases as [$notification, $type, $url]) {
            $data = $notification->toArray($user);
            $mail = $notification->toMail($user);
            $this->assertSame(['database', 'mail'], $notification->via($user));
            $this->assertSame($type, $data['notification_type']);
            $this->assertSame($url, $data['url']);
            $this->assertSame($url, $mail->actionUrl);
            $this->assertSame('【Certify LMS】'.$data['title'], $mail->subject);
            $this->assertContains($data['message'], $mail->introLines);
            $this->assertStringNotContainsString('転載しない', $data['message']);
            $this->assertStringContainsString('内容を確認する', (string) $mail->render());
        }
    }
}
