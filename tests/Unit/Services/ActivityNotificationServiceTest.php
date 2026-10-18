<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\SendDatabaseNotification;
use App\Jobs\SendNotificationMail;
use App\Models\ChatMessage;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Services\ActivityNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ActivityNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_filters_current_status_role_and_soft_deletion_and_deduplicates_recipients(): void
    {
        Queue::fake();
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
        app(ActivityNotificationService::class)->send(
            $ids, new ChatMessageReceivedNotification(ChatMessage::factory()->create()),
        );

        Queue::assertPushed(SendDatabaseNotification::class, 2);
        Queue::assertPushed(SendNotificationMail::class, 2);
        foreach ($excluded as $user) {
            $this->assertSame(0, $user->notifications()->count());
        }
        Queue::assertPushed(SendDatabaseNotification::class, function (SendDatabaseNotification $job) use ($student, $coach): bool {
            return in_array($job->userId, [$student->id, $coach->id], true);
        });
    }

    public function test_mail_failure_keeps_database_notifications_and_continues_other_recipients(): void
    {
        Queue::fake();
        $users = User::factory()->count(2)->create();

        app(ActivityNotificationService::class)->send(
            $users->pluck('id')->all(), new ChatMessageReceivedNotification(ChatMessage::factory()->create()),
        );

        Queue::assertPushed(SendDatabaseNotification::class, 2);
        Queue::assertPushed(SendNotificationMail::class, 2);
    }
}
