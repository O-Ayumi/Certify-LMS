<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\ChatMember;
use App\Models\ChatMessage;
use App\Models\ChatRoom;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Database\Seeders\CertificationCategorySeeder;
use Database\Seeders\CertificationSeeder;
use Database\Seeders\ChatSeeder;
use Database\Seeders\EnrollmentSeeder;
use Database\Seeders\MentoringSeeder;
use Database\Seeders\NotificationSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\QaThreadSeeder;
use Database\Seeders\UserLifecycleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Tests\TestCase;

class NotificationSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_connects_to_provided_seeders_and_all_four_notification_types_have_real_destinations(): void
    {
        $this->mock(MailChannel::class)->shouldNotReceive('send');
        $this->seed([
            UserSeeder::class, PlanSeeder::class, UserLifecycleSeeder::class,
            CertificationCategorySeeder::class, CertificationSeeder::class, QaThreadSeeder::class,
            EnrollmentSeeder::class, MentoringSeeder::class, ChatSeeder::class, NotificationSeeder::class,
        ]);

        $types = collect();
        foreach (['student@certify-lms.test', 'coach@certify-lms.test', 'coach2@certify-lms.test'] as $email) {
            $user = User::where('email', $email)->firstOrFail();
            $rows = $user->notifications()->get();
            $this->assertCount(25, $rows);
            $types = $types->merge($rows->pluck('data.notification_type'));
            $this->actingAs($user)->get(route('notifications.index'))->assertOk();
            foreach ($rows->unique('data.url') as $row) {
                $this->post(route('notifications.markAsRead', $row))->assertRedirect($row->data['url']);
                $this->get($row->data['url'])->assertOk();
            }
        }
        $this->assertEqualsCanonicalizing([
            'chat_message_received', 'qa_reply_received', 'meeting_reserved', 'meeting_canceled',
        ], $types->unique()->values()->all());
    }

    public function test_seeds_fixed_accounts_with_mixed_types_and_read_states_without_mail(): void
    {
        $student = User::factory()->create(['email' => 'student@certify-lms.test']);
        $coach = User::factory()->coach()->create(['email' => 'coach@certify-lms.test']);
        $coach2 = User::factory()->coach()->create(['email' => 'coach2@certify-lms.test']);
        $admin = User::factory()->admin()->create(['email' => 'admin@certify-lms.test']);
        $room = ChatRoom::factory()->create();
        foreach ([$student, $coach, $coach2] as $user) {
            ChatMember::factory()->create(['user_id' => $user->id, 'chat_room_id' => $room->id]);
            ChatMessage::factory()->create(['sender_user_id' => $user->id, 'chat_room_id' => $room->id]);
        }
        $thread = QaThread::factory()->create(['user_id' => $student->id]);
        QaReply::factory()->create(['user_id' => $coach->id, 'qa_thread_id' => $thread->id]);
        foreach ([$coach, $coach2] as $recipient) {
            Meeting::factory()->forCoach($recipient)->forStudent($student)->create();
            Meeting::factory()->forCoach($recipient)->forStudent($student)->canceled()
                ->create(['canceled_by_user_id' => $recipient->id]);
        }
        $this->mock(MailChannel::class)->shouldNotReceive('send');

        $this->seed(NotificationSeeder::class);

        foreach ([$student, $coach, $coach2] as $user) {
            $rows = $user->notifications()->get();
            $this->assertCount(25, $rows);
            $this->assertGreaterThan(0, $rows->whereNull('read_at')->count());
            $this->assertGreaterThan(0, $rows->whereNotNull('read_at')->count());
            $this->assertGreaterThan(1, $rows->pluck('data.notification_type')->unique()->count());
            foreach ($rows as $row) {
                $this->assertStringStartsWith(config('app.url'), $row->data['url']);
            }
            $this->actingAs($user)->get(route('notifications.index'))->assertOk()
                ->assertViewHas('notifications', fn ($rows) => $rows->hasMorePages());
        }
        $this->assertSame(0, $admin->notifications()->count());
    }
}
