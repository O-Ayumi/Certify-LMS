<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EditTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_edit_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.edit', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_edit_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.edit', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_edit_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->get(route('admin.plans.edit', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('admin.plans.edit', '00000000000000000000000000'));

        $response->assertNotFound();
    }

    public function test_admin_can_edit_draft_plan(): void
    {
        $plan = Plan::factory()->draft()->create();
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.plans.edit', $plan));
        $response->assertOk();
        $response->assertViewIs('plan.management.edit');
        $response->assertSee($plan->name);
    }

    public function test_admin_can_edit_published_plan(): void
    {
        $plan = Plan::factory()->published()->create();
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.plans.edit', $plan));
        $response->assertOk();
        $response->assertViewIs('plan.management.edit');
        $response->assertSee($plan->name);
    }

    public function test_admin_can_edit_archived_plan(): void
    {
        $plan = Plan::factory()->archived()->create();
        $admin = User::factory()->admin()->create();
        $response = $this->actingAs($admin)->get(route('admin.plans.edit', $plan));
        $response->assertOk();
        $response->assertViewIs('plan.management.edit');
        $response->assertSee($plan->name);
    }
}
