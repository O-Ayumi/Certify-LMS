<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_destroy_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->delete(route('admin.plans.destroy', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_destroy_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->delete(route('admin.plans.destroy', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_destroy_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->delete(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->delete(route('admin.plans.destroy', '00000000000000000000000000'));

        $response->assertNotFound();
    }

    public function test_delete_draft_plan(): void
    {
        $plan = Plan::factory()->draft()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertRedirect(route('admin.plans.index'));
        $response->assertSessionHas('success');
        $this->assertModelMissing($plan);
    }

    public function test_delete_published_plan(): void
    {
        $plan = Plan::factory()->published()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertConflict();
        $this->assertModelExists($plan);
    }

    public function test_delete_archived_plan(): void
    {
        $plan = Plan::factory()->archived()->create();
        $admin = User::factory()->admin()->create();

        $response = $this->actingAs($admin)->deleteJson(route('admin.plans.destroy', $plan));

        $response->assertConflict();
        $this->assertModelExists($plan);
    }

    public function test_delete_rejects_invited_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Invited]);

        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_soft_deleted_invited_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Invited]);
        $user->delete();
        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_inprogress_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::InProgress]);

        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_soft_deleted_inprogress_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::InProgress]);
        $user->delete();
        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_graduated_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Graduated]);

        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_soft_deleted_graduated_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Graduated]);
        $user->delete();
        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_withdrawn_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Withdrawn]);

        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_delete_rejects_soft_deleted_withdrawn_user_reference(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->withPlan($plan)->create(['status' => UserStatus::Withdrawn]);
        $user->delete();
        $response = $this->actingAs(User::factory()->admin()->create())->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $this->assertModelExists($plan);
        $this->assertDatabaseHas('users', ['id' => $user->id, 'plan_id' => $plan->id]);
    }

    public function test_history_alone_prevents_deletion_and_browser_receives_reason(): void
    {
        $plan = Plan::factory()->draft()->create();
        $log = UserPlanLog::factory()->create(['plan_id' => $plan->id]);
        $this->assertFalse($plan->users()->withTrashed()->exists());
        $this->actingAs(User::factory()->admin()->create());
        $response = $this->deleteJson(route('admin.plans.destroy', $plan));
        $response->assertConflict();
        $url = route('admin.plans.show', $plan);
        $response = $this->from($url)->delete(route('admin.plans.destroy', $plan));
        $response->assertRedirect($url);
        $response->assertSessionHas('error');
        $this->assertModelExists($plan);
        $this->assertModelExists($log);
    }
}
