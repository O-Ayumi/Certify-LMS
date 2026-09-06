<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\CertificationStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_a_published_thread_and_replies_in_oldest_order(): void
    {
        $student = User::factory()->student()->create(['name' => '質問者']);
        $replyAuthor = User::factory()->student()->create(['name' => '回答者']);
        $certification = Certification::factory()->published()->create(['name' => '公開資格']);
        $thread = QaThread::factory()->for($certification)->for($student)->create([
            'title' => '質問タイトル',
            'body' => '質問本文',
        ]);
        $newerReply = QaReply::factory()->for($thread, 'thread')->for($replyAuthor)->create([
            'body' => '後の回答',
            'created_at' => now()->addMinute(),
        ]);
        $olderReply = QaReply::factory()->for($thread, 'thread')->for($replyAuthor)->create([
            'body' => '先の回答',
            'created_at' => now(),
        ]);

        $response = $this->actingAs($student)->get(route('qa-board.show', $thread));

        $response->assertOk()->assertViewIs('qa-thread.show');
        $response->assertSee(['質問者', '公開資格', '質問タイトル', '質問本文', '先の回答', '後の回答']);
        $this->assertSame([$olderReply->id, $newerReply->id], $response->viewData('thread')->replies->pluck('id')->all());
    }

    public function test_assigned_coach_can_view_a_published_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->create();

        $this->actingAs($coach)
            ->get(route('qa-board.show', $thread))
            ->assertOk();
    }

    public function test_unassigned_coach_cannot_view_a_thread(): void
    {
        $coach = User::factory()->coach()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($coach)
            ->get(route('qa-board.show', $thread))
            ->assertForbidden();
    }

    public function test_student_cannot_view_a_thread_for_an_unpublished_certification(): void
    {
        $thread = QaThread::factory()->create();
        $thread->certification()->update(['status' => CertificationStatus::Archived]);

        $this->actingAs(User::factory()->student()->create())
            ->get(route('qa-board.show', $thread))
            ->assertNotFound();
    }

    public function test_admin_can_view_an_unpublished_thread_from_the_admin_route(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $thread->certification()->update(['status' => CertificationStatus::Archived]);

        $response = $this->actingAs($admin)->get(route('admin.qa-board.show', $thread));

        $response->assertOk()->assertViewIs('qa-thread.show');
        $response->assertSee(route('admin.qa-board.index'));
        $response->assertSee(route('admin.qa-board.destroy', $thread));
        $response->assertSee('管理者は閲覧 + モデレーション削除のみ可能');
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $thread = QaThread::factory()->create();

        $this->get(route('qa-board.show', $thread))
            ->assertRedirect(route('login'));
    }

    public function test_nonexistent_thread_returns_not_found(): void
    {
        $this->actingAs(User::factory()->student()->create())
            ->get('/qa-board/'.str_repeat('0', 26))
            ->assertNotFound();
    }
}
