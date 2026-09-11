<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Services\ActivityNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ActivityNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_current_status_role_and_soft_deletion_and_deduplicates_recipients(): void
    {
        $student = User::factory()->create();
        $coach = User::factory()->coach()->create();
        $excluded = collect([
            User::factory()->admin()->create(),
            User::factory()->invited()->create(),
            User::factory()->graduated()->create(),
            User::factory()->withdrawn()->create(),
            User::factory()->coach()->graduated()->create(),
            User::factory()->create(),
        ]);
        $excluded->last()->delete();
        $ids = [$student->id, $coach->id, $student->id, ...$excluded->pluck('id')->all()];
        $this->mock(MailChannel::class)->shouldReceive('send')->twice();

        app(ActivityNotificationService::class)->send(
            $ids, new ChatMessageReceivedNotification(ChatMessage::factory()->create()),
        );

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(1, $coach->notifications()->count());
        foreach ($excluded as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        $this->assertDatabaseCount('notifications', 2);
    }

    public function test_mail_failure_keeps_database_notifications_and_continues_other_recipients(): void
    {
        $users = User::factory()->count(2)->create();
        $this->mock(MailChannel::class)->shouldReceive('send')->twice()
            ->andThrow(new \RuntimeException('mail unavailable'));
        Log::shouldReceive('error')->twice()->withArgs(
            fn ($message, $context) => isset($context['user_id'], $context['notification_type'], $context['exception'])
        );

        app(ActivityNotificationService::class)->send(
            $users->pluck('id')->all(), new ChatMessageReceivedNotification(ChatMessage::factory()->create()),
        );

        foreach ($users as $user) {
            $this->assertSame(1, $user->unreadNotifications()->count());
        }
    }
}
