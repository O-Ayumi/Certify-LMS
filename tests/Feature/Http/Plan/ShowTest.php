<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_plan_details_and_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $creator = User::factory()->admin()->create(['name' => 'プラン作成担当']);
        $plan = Plan::factory()->create([
            'name' => '詳細確認用プラン',
            'description' => '詳細確認用の説明',
            'duration_days' => 90,
            'default_meeting_quota' => 12,
            'created_by_user_id' => $creator->id,
            'updated_by_user_id' => $admin->id,
            'created_at' => '2026-01-02 03:04:00',
        ]);

        $response = $this->actingAs($admin)->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $response->assertViewIs('plan.management.show');
        $response->assertSee($plan->name);
        $response->assertSee($plan->description);
        $response->assertSee('90');
        $response->assertSee('12');
        $response->assertSee($creator->name);
        $response->assertSee($admin->name);
        $response->assertSee('2026-01-02 03:04');
        $response->assertSee('このプランを受講中のユーザーはまだいません。');
    }

    public function test_show_lists_currently_linked_users_in_all_statuses_except_soft_deleted_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();
        $expectedIds = [];
        $emails = [];
        foreach (UserStatus::cases() as $status) {
            $user = User::factory()->student()->withPlan($plan)->create([
                'status' => $status,
                'email' => $status->value.'@example.test',
                'plan_expires_at' => '2027-02-03 00:00:00',
                'max_meetings' => 17,
            ]);
            $expectedIds[] = $user->id;
            $emails[] = $user->email;
        }
        $deleted = User::factory()->student()->withPlan($plan)->create();
        $deleted->delete();
        $other = User::factory()->student()->withPlan(Plan::factory()->create())->create();
        $this->actingAs($admin);

        $response = $this->get(route('admin.plans.show', $plan));

        $response->assertOk();
        $shown = $response->viewData('plan');
        $this->assertEqualsCanonicalizing($expectedIds, $shown->users->modelKeys());
        $response->assertDontSee($deleted->email);
        $response->assertDontSee($other->email);
        $response->assertSee('2027-02-03');
        $response->assertSee('17');
        foreach ($emails as $email) {
            $response->assertSee($email);
        }
    }

    public function test_student_cannot_show_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_show_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.show', $plan));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_show_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->get(route('admin.plans.show', $plan));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->get(route('admin.plans.show', '00000000000000000000000000'));

        $response->assertNotFound();
    }
}
