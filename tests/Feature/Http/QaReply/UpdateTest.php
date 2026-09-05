<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_owner_can_update_body_only(): void
    {
        $student = User::factory()->student()->create();
        $reply = QaReply::factory()->for(QaThread::factory(), 'thread')->for($student)->create();
        $spoofed = User::factory()->student()->create();

        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$reply->thread, $reply]), [
                'body' => '更新された回答',
                'user_id' => $spoofed->id,
            ])
            ->assertRedirect(route('qa-board.show', $reply->thread));

        $this->assertDatabaseHas('qa_replies', ['id' => $reply->id, 'body' => '更新された回答', 'user_id' => $student->id]);
    }

    public function test_assigned_coach_can_update_own_reply(): void
    {
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $certification = $replyThread = QaThread::factory()->create()->certification;
        $certification->update(['status' => 'published']);
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($coach)->create();

        $this->actingAs($coach)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => 'コーチ更新'])
            ->assertRedirect(route('qa-board.show', $thread));
        unset($replyThread);
    }

    public function test_other_user_cannot_update_and_cross_thread_reply_is_not_found(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($owner)->create();

        $this->actingAs($other)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '拒否'])
            ->assertForbidden();
        $this->actingAs($owner)
            ->patch(route('qa-board.replies.update', [$otherThread, $reply]), ['body' => '不正'])
            ->assertNotFound();
    }

    public function test_reply_cannot_be_updated_after_certification_is_unpublished(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($student)->create();
        $thread->certification()->update(['status' => 'archived']);

        $this->actingAs($student)
            ->patch(route('qa-board.replies.update', [$thread, $reply]), ['body' => '更新'])
            ->assertNotFound();
    }
}
