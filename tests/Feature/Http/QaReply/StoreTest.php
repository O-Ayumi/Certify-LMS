<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaReply;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_reply_and_request_user_id_is_ignored(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $spoofed = User::factory()->student()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $thread), [
                'body' => '回答本文',
                'user_id' => $spoofed->id,
            ])
            ->assertRedirect(route('qa-board.show', $thread));

        $this->assertDatabaseHas('qa_replies', [
            'qa_thread_id' => $thread->id,
            'user_id' => $student->id,
            'body' => '回答本文',
        ]);
    }

    public function test_assigned_coach_can_reply_but_unassigned_coach_and_admin_cannot(): void
    {
        $admin = User::factory()->admin()->create();
        $assigned = User::factory()->coach()->create();
        $unassigned = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($assigned->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->create();

        $this->actingAs($assigned)->post(route('qa-board.replies.store', $thread), ['body' => '担当回答'])->assertRedirect();
        $this->actingAs($unassigned)->post(route('qa-board.replies.store', $thread), ['body' => '担当外回答'])->assertForbidden();
        $this->actingAs($admin)->post(route('qa-board.replies.store', $thread), ['body' => '管理者回答'])->assertForbidden();
    }

    public function test_archived_thread_cannot_receive_a_reply_and_resolved_state_is_unchanged(): void
    {
        $student = User::factory()->student()->create();
        $resolved = QaThread::factory()->resolved()->create();

        $this->actingAs($student)
            ->post(route('qa-board.replies.store', $resolved), ['body' => '回答'])
            ->assertRedirect();
        $this->assertSame(QaThreadStatus::Resolved, $resolved->fresh()->status);

        $archived = QaThread::factory()->create();
        $archived->certification()->update(['status' => 'archived']);
        $this->post(route('qa-board.replies.store', $archived), ['body' => '回答'])->assertNotFound();
    }

    public function test_body_is_required_and_limited_to_5000_characters(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)->post(route('qa-board.replies.store', $thread), ['body' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body');
        $this->post(route('qa-board.replies.store', $thread), ['body' => " \t　"])
            ->assertSessionHasErrors('body');
    }
}
