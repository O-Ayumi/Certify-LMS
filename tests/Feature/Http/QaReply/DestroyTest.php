<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_owner_can_delete_a_reply(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($student)->create();

        $this->actingAs($student)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_delete_any_reply_from_admin_route(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.replies.destroy', [$thread, $reply]))
            ->assertRedirect(route('admin.qa-board.show', $thread));
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_other_user_cannot_delete_and_cross_thread_reply_is_not_found(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $otherThread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($owner)->create();

        $this->actingAs($other)
            ->delete(route('qa-board.replies.destroy', [$thread, $reply]))
            ->assertForbidden();
        $this->actingAs($owner)
            ->delete(route('qa-board.replies.destroy', [$otherThread, $reply]))
            ->assertNotFound();
    }
}
