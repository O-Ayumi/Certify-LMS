<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnarchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_unarchive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.unarchive', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_unarchive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.unarchive', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_unarchive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->post(route('admin.plans.unarchive', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post(route('admin.plans.unarchive', '00000000000000000000000000'));

        $response->assertNotFound();
    }

    public function test_unarchive_rejects_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.unarchive', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_unarchive_rejects_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.unarchive', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_unarchive_succeeds_for_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.unarchive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $plan->refresh();
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertNotEquals($before['updated_at'], $plan->getRawOriginal('updated_at'));
        $after = $plan->getRawOriginal();
        unset($before['status'], $before['updated_by_user_id'], $before['updated_at']);
        unset($after['status'], $after['updated_by_user_id'], $after['updated_at']);
        $this->assertSame($before, $after);
    }
}
