<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublishTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_publish_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_publish_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.publish', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_publish_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->post(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->post(route('admin.plans.publish', '00000000000000000000000000'));

        $response->assertNotFound();
    }

    public function test_publish_succeeds_for_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->draft()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $plan->refresh();
        $this->assertSame(PlanStatus::Published, $plan->status);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertNotEquals($before['updated_at'], $plan->getRawOriginal('updated_at'));
        $after = $plan->getRawOriginal();
        unset($before['status'], $before['updated_by_user_id'], $before['updated_at']);
        unset($after['status'], $after['updated_by_user_id'], $after['updated_at']);
        $this->assertSame($before, $after);
    }

    public function test_publish_rejects_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->published()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_publish_rejects_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->archived()->create(['updated_at' => '2026-01-01']);
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->actingAs($admin)->postJson(route('admin.plans.publish', $plan));

        $response->assertConflict();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_invalid_browser_transition_redirects_with_reason(): void
    {
        $plan = Plan::factory()->published()->create();
        $url = route('admin.plans.show', $plan);
        $response = $this->actingAs(User::factory()->admin()->create())->from($url)
            ->post(route('admin.plans.publish', $plan));
        $response->assertRedirect($url);
        $response->assertSessionHas('error', '下書きのプランのみ公開できます。');
        $this->assertSame(PlanStatus::Published, $plan->fresh()->status);
    }
}
