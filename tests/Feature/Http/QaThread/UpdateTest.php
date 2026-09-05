<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_title_and_body_only(): void
    {
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($owner)->create();

        $response = $this->actingAs($owner)->patch(route('qa-board.update', $thread), [
            'title' => '更新タイトル',
            'body' => '更新本文',
            'certification_id' => Certification::factory()->published()->create()->id,
            'user_id' => $other->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);

        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'title' => '更新タイトル',
            'body' => '更新本文',
            'certification_id' => $certification->id,
            'user_id' => $owner->id,
            'status' => QaThreadStatus::Open->value,
        ]);
    }

    public function test_other_student_coach_and_admin_cannot_update(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        foreach ([User::factory()->student(), User::factory()->coach(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())
                ->patch(route('qa-board.update', $thread), ['title' => '変更', 'body' => '本文'])
                ->assertForbidden();
        }
    }

    public function test_owner_cannot_update_after_certification_is_unpublished(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        $thread->certification()->update(['status' => 'archived']);

        $this->actingAs($owner)
            ->patch(route('qa-board.update', $thread), ['title' => '変更', 'body' => '本文'])
            ->assertNotFound();
    }
}
