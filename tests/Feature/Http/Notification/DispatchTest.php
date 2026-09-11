<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Enums\MeetingStatus;
use App\Models\Certification;
use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\CoachAvailability;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\QaThread;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class DispatchTest extends TestCase
{
    use RefreshDatabase;

    private function attachCoach(Certification $certification, User $coach): void
    {
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
            'unassigned_at' => null,
        ]);
    }

    public function test_chat_and_reply_http_endpoints_send_notifications_but_invalid_posts_do_not(): void
    {
        Queue::fake();
        $student = User::factory()->create();
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->create();
        $room = ChatRoom::factory()->for($enrollment)->create();
        foreach ([$student, $coach] as $user) {
            ChatMember::factory()->create(['chat_room_id' => $room->id, 'user_id' => $user->id]);
        }
        $thread = QaThread::factory()->create(['user_id' => $student->id, 'certification_id' => $certification->id]);
        $this->mock(MailChannel::class)->shouldReceive('send')->twice();

        $this->actingAs($student)->post(route('chat.storeMessage', $room), ['body' => 'こんにちは'])
            ->assertRedirect(route('chat.show', $room));
        $this->assertSame('chat_message_received', $coach->notifications()->sole()->data['notification_type']);
        $this->assertSame(0, $student->notifications()->count());

        $this->actingAs($coach)->post(route('qa-board.replies.store', $thread), ['body' => '回答です'])
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame('qa_reply_received', $student->notifications()->sole()->data['notification_type']);

        $this->post(route('qa-board.replies.store', $thread), ['body' => ''])->assertSessionHasErrors('body');
        $this->post(route('chat.storeMessage', $room), ['body' => ''])->assertSessionHasErrors('body');
        $this->actingAs(User::factory()->create())
            ->post(route('chat.storeMessage', $room), ['body' => '不正投稿'])->assertForbidden();
        $this->assertDatabaseCount('notifications', 2);
        $this->assertDatabaseCount('chat_messages', 1);
        $this->assertDatabaseCount('qa_replies', 1);
    }

    public function test_booking_notifies_assigned_coach_and_cancellation_notifies_the_other_party(): void
    {
        $student = User::factory()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $this->attachCoach($certification, $coach);
        CoachAvailability::factory()->forCoach($coach)->onDay(1)->timeRange('09:00:00', '18:00:00')->create();
        $enrollment = Enrollment::factory()->for($student, 'user')->for($certification)->learning()->create();
        $scheduledAt = now()->startOfDay()->next(Carbon::MONDAY)->setTime(10, 0);
        $this->mock(MailChannel::class)->shouldReceive('send')->times(3);

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => $scheduledAt->format('Y-m-d H:i:s'), 'topic' => '相談です',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $meeting = Meeting::sole();
        $this->assertSame('meeting_reserved', $coach->notifications()->sole()->data['notification_type']);
        $this->assertSame(0, $student->notifications()->count());

        $this->post(route('meetings.cancel', $meeting))->assertRedirect();
        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertSame(2, $coach->notifications()->count());
        $this->assertSame(0, $student->notifications()->count());
        $this->post(route('meetings.cancel', $meeting))->assertForbidden();
        $this->assertSame(2, $coach->notifications()->count());

        $second = Meeting::factory()->forCoach($coach)->forEnrollment($enrollment)
            ->create(['scheduled_at' => $scheduledAt->copy()->addDay()]);
        $this->actingAs($coach)->post(route('meetings.cancel', $second))->assertRedirect();
        $this->assertSame('meeting_canceled', $student->notifications()->sole()->data['notification_type']);
        $this->assertSame(2, $coach->notifications()->count());
    }

    public function test_invalid_booking_and_unauthorized_cancellation_do_not_notify(): void
    {
        $student = User::factory()->create(['max_meetings' => 0]);
        $enrollment = Enrollment::factory()->for($student, 'user')->learning()->create();
        $meeting = Meeting::factory()->create();
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        $this->actingAs($student)->post(route('meetings.store', $enrollment), [
            'scheduled_at' => now()->addDays(2)->startOfHour()->format('Y-m-d H:i:s'),
            'topic' => '回数不足',
        ])->assertSessionHas('error');
        $this->post(route('meetings.cancel', $meeting))->assertForbidden();
        $this->assertSame(MeetingStatus::Reserved, $meeting->fresh()->status);
        $this->assertDatabaseCount('meetings', 1);
        $this->assertDatabaseCount('notifications', 0);
    }
}
