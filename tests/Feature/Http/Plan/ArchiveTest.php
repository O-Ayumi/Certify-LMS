<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_archive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.archive', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_archive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.archive', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_archive_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->post(route('admin.plans.archive', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post(route('admin.plans.archive', '00000000000000000000000000'));

        $response->assertNotFound();
    }

    public function test_archive_rejects_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_archive_succeeds_for_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $plan->refresh();
        $this->assertSame(PlanStatus::Archived, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertNotEquals($before['updated_at'], $plan->getRawOriginal('updated_at'));
        $after = $plan->getRawOriginal();
        unset($before['status'], $before['updated_by_user_id'], $before['updated_at']);
        unset($after['status'], $after['updated_by_user_id'], $after['updated_at']);
        $this->assertSame($before, $after);
    }

    public function test_archive_rejects_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.archive', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_archive_removes_choices_without_changing_contracts_or_history(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $plan = Plan::factory()->published()->create();
        $remaining = Plan::factory()->published()->create();
        Plan::factory()->draft()->create();
        Plan::factory()->archived()->create();
        $user = User::factory()->withPlan($plan)->create();
        $log = UserPlanLog::factory()->create(['user_id' => $user->id, 'plan_id' => $plan->id]);
        $beforeUser = $user->fresh()->getRawOriginal();
        $beforeLog = $log->fresh()->getRawOriginal();

        $response = $this->get(route('admin.users.index'));
        $response->assertOk();
        $this->assertEqualsCanonicalizing([$plan->id, $remaining->id], $response->viewData('inviteFormPlans')->modelKeys());
        $response = $this->get(route('admin.users.show', $user));
        $response->assertOk();
        $this->assertEqualsCanonicalizing([$plan->id, $remaining->id], $response->viewData('plans')->modelKeys());

        $response = $this->post(route('admin.plans.archive', $plan));
        $response->assertRedirect();

        $response = $this->get(route('admin.users.index'));
        $response->assertOk();
        $this->assertEqualsCanonicalizing([$remaining->id], $response->viewData('inviteFormPlans')->modelKeys());
        $response = $this->get(route('admin.users.show', $user));
        $response->assertOk();
        $this->assertEqualsCanonicalizing([$remaining->id], $response->viewData('plans')->modelKeys());
        $this->assertSame($beforeUser, $user->fresh()->getRawOriginal());
        $this->assertSame($beforeLog, $log->fresh()->getRawOriginal());
    }
}
