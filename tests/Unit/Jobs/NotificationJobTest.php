<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SendDatabaseNotification;
use App\Jobs\SendNotificationMail;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Tests\TestCase;

final class NotificationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_database_notification_job_saves_one_notification(): void
    {
        $user = User::factory()->create();
        $notification = new ChatMessageReceivedNotification(ChatMessage::factory()->create());

        (new SendDatabaseNotification($user->id, $notification))->handle();

        $this->assertSame(1, $user->notifications()->count());
    }

    public function test_mail_job_sends_only_through_the_mail_channel(): void
    {
        $user = User::factory()->create();
        $notification = new ChatMessageReceivedNotification(ChatMessage::factory()->create());
        $this->mock(MailChannel::class)->shouldReceive('send')->once();

        (new SendNotificationMail($user->id, $notification))->handle();

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_mail_job_has_retry_policy_without_replaying_database_notification(): void
    {
        $this->assertSame(3, (new SendNotificationMail('user-id', new ChatMessageReceivedNotification(ChatMessage::factory()->create())))->tries);
        $this->assertSame([60, 300, 900], (new SendNotificationMail('user-id', new ChatMessageReceivedNotification(ChatMessage::factory()->create())))->backoff);
        $this->assertSame(1, (new SendDatabaseNotification('user-id', new ChatMessageReceivedNotification(ChatMessage::factory()->create())))->tries);
    }
}
