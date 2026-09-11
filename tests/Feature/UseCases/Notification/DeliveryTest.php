<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Notification;

use App\Events\MeetingCanceled;
use App\Events\MeetingReserved;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\Chat\StoreMessageAction;
use App\UseCases\QaReply\StoreAction;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/** 実トランザクションを使い、最外側のコミット・ロールバックと通知の関係を検証する。 */
class DeliveryTest extends TestCase
{
    use DatabaseMigrations;

    public function test_chat_notifies_other_members_only_after_outer_commit(): void
    {
        Queue::fake();
        $student = User::factory()->create();
        $coaches = User::factory()->coach()->count(2)->create();
        $room = ChatRoom::factory()->create();
        foreach ([$student, ...$coaches] as $user) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $user->id]);
        }
        $this->mock(MailChannel::class)->shouldReceive('send')->twice()
            ->andReturnUsing(function () {
                $this->assertSame(0, DB::transactionLevel());
            });

        DB::beginTransaction();
        $message = (new StoreMessageAction)($student, $room, ['body' => '新着メッセージ']);
        $this->assertDatabaseCount('notifications', 0);
        DB::commit();

        $this->assertModelExists($message);
        $this->assertSame(0, $student->notifications()->count());
        foreach ($coaches as $coach) {
            $this->assertSame('chat_message_received', $coach->notifications()->sole()->data['notification_type']);
        }
    }

    public function test_reply_notifies_author_only_and_self_reply_is_excluded(): void
    {
        $author = User::factory()->create();
        $responder = User::factory()->create();
        $thread = QaThread::factory()->create(['user_id' => $author->id]);
        $this->mock(MailChannel::class)->shouldReceive('send')->once();

        (new StoreAction)($responder, $thread, '回答です');
        (new StoreAction)($author, $thread, '自己返信です');

        $this->assertSame('qa_reply_received', $author->notifications()->sole()->data['notification_type']);
        $this->assertSame(0, $responder->notifications()->count());
        $this->assertDatabaseCount('notifications', 1);
    }

    public function test_rollback_discards_notifications_for_all_four_events(): void
    {
        Queue::fake();
        $author = User::factory()->create();
        $responder = User::factory()->coach()->create();
        $thread = QaThread::factory()->create(['user_id' => $author->id]);
        $room = ChatRoom::factory()->create();
        ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $author->id]);
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        DB::beginTransaction();
        (new StoreMessageAction)($responder, $room, ['body' => '取り消す投稿']);
        (new StoreAction)($responder, $thread, '取り消す回答');
        $meeting = Meeting::factory()->forCoach($responder)->forStudent($author)->create();
        DB::afterCommit(fn () => MeetingReserved::dispatch($meeting));
        $meeting->update(['status' => 'canceled', 'canceled_by_user_id' => $author->id]);
        DB::afterCommit(fn () => MeetingCanceled::dispatch($meeting));
        $this->assertDatabaseCount('notifications', 0);
        DB::rollBack();

        $this->assertDatabaseCount('notifications', 0);
        $this->assertDatabaseCount('chat_messages', 0);
        $this->assertDatabaseCount('qa_replies', 0);
        $this->assertDatabaseCount('meetings', 0);
    }

    public function test_mail_failure_does_not_rollback_reply_and_its_database_notification(): void
    {
        $author = User::factory()->create();
        $thread = QaThread::factory()->create(['user_id' => $author->id]);
        $this->mock(MailChannel::class)->shouldReceive('send')->once()
            ->andThrow(new \RuntimeException('mail unavailable'));
        Log::shouldReceive('error')->once();

        $reply = (new StoreAction)(User::factory()->create(), $thread, '保存される回答');

        $this->assertModelExists($reply);
        $this->assertSame(1, $author->unreadNotifications()->count());
        $this->assertSame(0, DB::transactionLevel());
    }

    public function test_recipient_status_is_checked_at_commit_time(): void
    {
        $author = User::factory()->create();
        $thread = QaThread::factory()->create(['user_id' => $author->id]);
        $responder = User::factory()->create();
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        DB::beginTransaction();
        (new StoreAction)($responder, $thread, '回答');
        $author->update(['status' => 'graduated']);
        DB::commit();

        $this->assertDatabaseCount('qa_replies', 1);
        $this->assertDatabaseCount('notifications', 0);
    }
}
