<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_delete_a_thread_without_replies(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->delete(route('qa-board.destroy', $thread))
            ->assertRedirect(route('qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
    }

    public function test_owner_cannot_delete_a_thread_with_replies(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($owner)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_delete_any_thread_and_is_returned_to_admin_index(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($admin)
            ->delete(route('admin.qa-board.destroy', $thread))
            ->assertRedirect(route('admin.qa-board.index'));

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }
}
