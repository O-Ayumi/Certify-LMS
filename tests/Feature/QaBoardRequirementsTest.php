<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaBoardRequirementsTest extends TestCase
{
    use RefreshDatabase;

    public function test_keyword_search_matches_title_question_body_or_reply_body(): void
    {
        $user = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $byTitle = QaThread::factory()->for($certification)->create(['title' => 'needle title']);
        $byQuestion = QaThread::factory()->for($certification)->create(['body' => 'needle question']);
        $byReply = QaThread::factory()->for($certification)->create();
        QaReply::factory()->for($byReply, 'thread')->create(['body' => 'needle reply']);
        QaThread::factory()->for($certification)->create(['title' => 'unrelated', 'body' => 'unrelated']);

        $response = $this->actingAs($user)->get(route('qa-board.index', ['keyword' => 'needle']));

        $response->assertOk();
        $this->assertEqualsCanonicalizing(
            [$byTitle->id, $byQuestion->id, $byReply->id],
            $response->viewData('threads')->pluck('id')->all(),
        );
    }

    public function test_question_cannot_be_deleted_after_a_reply_is_posted(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->actingAs($author)->delete(route('qa-board.destroy', $thread))->assertForbidden();
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }

    public function test_admin_can_delete_a_question_even_when_it_has_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        QaReply::factory()->for($thread, 'thread')->count(2)->create();

        $this->actingAs($admin)->delete(route('admin.qa-board.destroy', $thread))->assertRedirect(route('admin.qa-board.index'));
        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['qa_thread_id' => $thread->id]);
    }

    public function test_coach_cannot_create_a_question_but_can_reply_to_an_assigned_certification(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->resolved()->create();

        $this->actingAs($coach)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'コーチによる質問',
            'body' => 'コーチは質問を投稿できません。',
        ])->assertForbidden();

        $this->post(route('qa-board.replies.store', $thread), ['body' => '解決済みでも回答できます。'])->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_replies', ['qa_thread_id' => $thread->id, 'user_id' => $coach->id]);
    }

    public function test_resolve_is_idempotent_and_keeps_the_first_timestamp(): void
    {
        $author = User::factory()->student()->create();
        $thread = QaThread::factory()->for($author)->create();
        $this->actingAs($author)->post(route('qa-board.resolve', $thread))->assertRedirect();
        $first = $thread->fresh()->resolved_at;

        $this->travel(10)->minutes();
        $this->post(route('qa-board.resolve', $thread))->assertRedirect();

        $this->assertTrue($first->equalTo($thread->fresh()->resolved_at));
    }

    public function test_full_width_or_ascii_whitespace_only_is_rejected(): void
    {
        $author = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $this->actingAs($author)->post(route('qa-board.store'), [
            'certification_id' => $certification->id, 'title' => " \t　", 'body' => "\n　 ",
        ])->assertSessionHasErrors(['title', 'body']);
    }

    public function test_unpublished_certification_is_404_for_student_and_coach(): void
    {
        $thread = QaThread::factory()->create();
        $thread->certification()->update(['status' => CertificationStatus::Archived]);
        foreach ([User::factory()->student()->create(), User::factory()->coach()->create()] as $user) {
            $this->actingAs($user)->get(route('qa-board.show', $thread))->assertNotFound();
        }
    }
}
