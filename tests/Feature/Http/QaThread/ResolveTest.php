<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_resolve_and_unresolve_a_thread(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();

        $this->actingAs($owner)
            ->post(route('qa-board.resolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $this->assertSame(QaThreadStatus::Resolved, $thread->fresh()->status);
        $this->assertNotNull($thread->fresh()->resolved_at);

        $this->post(route('qa-board.unresolve', $thread))
            ->assertRedirect(route('qa-board.show', $thread));
        $fresh = $thread->fresh();
        $this->assertSame(QaThreadStatus::Open, $fresh->status);
        $this->assertNull($fresh->resolved_at);
    }

    public function test_repeated_state_changes_are_idempotent(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->resolved()->create();
        $resolvedAt = $thread->resolved_at;

        $this->actingAs($owner)->post(route('qa-board.resolve', $thread));
        $this->assertTrue($resolvedAt->equalTo($thread->fresh()->resolved_at));

        $this->post(route('qa-board.unresolve', $thread));
        $resolvedAt = $thread->fresh()->resolved_at;
        $this->post(route('qa-board.unresolve', $thread));
        $this->assertNull($resolvedAt);
        $this->assertNull($thread->fresh()->resolved_at);
    }

    public function test_other_student_coach_and_admin_cannot_change_state(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        foreach ([User::factory()->student(), User::factory()->coach(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())
                ->post(route('qa-board.resolve', $thread))
                ->assertForbidden();
        }
    }
}
