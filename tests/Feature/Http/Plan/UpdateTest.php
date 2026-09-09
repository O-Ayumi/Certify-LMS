<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_update_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->put(route('admin.plans.update', $plan), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_update_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->put(route('admin.plans.update', $plan), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_update_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->put(route('admin.plans.update', $plan), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_unknown_plan_returns_not_found(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $response = $this->put(route('admin.plans.update', '00000000000000000000000000'), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertNotFound();
    }

    public function test_update_preserves_contract_and_metadata_for_draft_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $plan = Plan::factory()->create(['status' => PlanStatus::Draft]);
        $creator = $plan->created_by_user_id;
        $user = User::factory()->withPlan($plan)->create();
        $log = UserPlanLog::factory()->create(['plan_id' => $plan->id, 'user_id' => $user->id]);
        $beforeUser = $user->fresh()->getRawOriginal();
        $beforeLog = $log->fresh()->getRawOriginal();
        $data = [
            'name' => str_repeat('あ', 100), 'description' => str_repeat('説', 2000),
            'duration_days' => 3650, 'default_meeting_quota' => 1000, 'sort_order' => 4294967295,
        ];
        $response = $this->put(route('admin.plans.update', $plan), $data + [
            'status' => 'invalid', 'created_by_user_id' => $admin->id, 'updated_by_user_id' => $creator,
        ]);
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plans', $data + [
            'id' => $plan->id, 'status' => PlanStatus::Draft->value,
            'created_by_user_id' => $creator, 'updated_by_user_id' => $admin->id,
        ]);
        $response = $this->put(route('admin.plans.update', $plan), ['name' => '検証用プラン', 'description' => null, 'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => null]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id, 'name' => '検証用プラン', 'description' => null,
            'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => 0,
        ]);
        $this->assertSame($beforeUser, $user->fresh()->getRawOriginal());
        $this->assertSame($beforeLog, $log->fresh()->getRawOriginal());
    }

    public function test_update_preserves_contract_and_metadata_for_published_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $plan = Plan::factory()->create(['status' => PlanStatus::Published]);
        $creator = $plan->created_by_user_id;
        $user = User::factory()->withPlan($plan)->create();
        $log = UserPlanLog::factory()->create(['plan_id' => $plan->id, 'user_id' => $user->id]);
        $beforeUser = $user->fresh()->getRawOriginal();
        $beforeLog = $log->fresh()->getRawOriginal();
        $data = [
            'name' => str_repeat('あ', 100), 'description' => str_repeat('説', 2000),
            'duration_days' => 3650, 'default_meeting_quota' => 1000, 'sort_order' => 4294967295,
        ];
        $response = $this->put(route('admin.plans.update', $plan), $data + [
            'status' => 'invalid', 'created_by_user_id' => $admin->id, 'updated_by_user_id' => $creator,
        ]);
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plans', $data + [
            'id' => $plan->id, 'status' => PlanStatus::Published->value,
            'created_by_user_id' => $creator, 'updated_by_user_id' => $admin->id,
        ]);
        $response = $this->put(route('admin.plans.update', $plan), ['name' => '検証用プラン', 'description' => null, 'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => null]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id, 'name' => '検証用プラン', 'description' => null,
            'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => 0,
        ]);
        $this->assertSame($beforeUser, $user->fresh()->getRawOriginal());
        $this->assertSame($beforeLog, $log->fresh()->getRawOriginal());
    }

    public function test_update_preserves_contract_and_metadata_for_archived_plan(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);

        $plan = Plan::factory()->create(['status' => PlanStatus::Archived]);
        $creator = $plan->created_by_user_id;
        $user = User::factory()->withPlan($plan)->create();
        $log = UserPlanLog::factory()->create(['plan_id' => $plan->id, 'user_id' => $user->id]);
        $beforeUser = $user->fresh()->getRawOriginal();
        $beforeLog = $log->fresh()->getRawOriginal();
        $data = [
            'name' => str_repeat('あ', 100), 'description' => str_repeat('説', 2000),
            'duration_days' => 3650, 'default_meeting_quota' => 1000, 'sort_order' => 4294967295,
        ];
        $response = $this->put(route('admin.plans.update', $plan), $data + [
            'status' => 'invalid', 'created_by_user_id' => $admin->id, 'updated_by_user_id' => $creator,
        ]);
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('plans', $data + [
            'id' => $plan->id, 'status' => PlanStatus::Archived->value,
            'created_by_user_id' => $creator, 'updated_by_user_id' => $admin->id,
        ]);
        $response = $this->put(route('admin.plans.update', $plan), ['name' => '検証用プラン', 'description' => null, 'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => null]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', [
            'id' => $plan->id, 'name' => '検証用プラン', 'description' => null,
            'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => 0,
        ]);
        $this->assertSame($beforeUser, $user->fresh()->getRawOriginal());
        $this->assertSame($beforeLog, $log->fresh()->getRawOriginal());
    }

    public function test_update_rejects_name_required(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['name'] = '';

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_name_length(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['name'] = str_repeat('あ', 101);

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_name_type(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['name'] = ['x'];

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_description_length(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['description'] = str_repeat('あ', 2001);

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_description_type(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['description'] = ['x'];

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_duration_required(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['duration_days'] = null;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_duration_min(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['duration_days'] = 0;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_duration_max(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['duration_days'] = 3651;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_duration_integer(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['duration_days'] = 1.5;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_quota_required(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['default_meeting_quota'] = null;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_quota_min(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['default_meeting_quota'] = -1;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_quota_max(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['default_meeting_quota'] = 1001;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_quota_integer(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['default_meeting_quota'] = 1.5;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_order_min(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['sort_order'] = -1;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_order_max(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['sort_order'] = 4294967296;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_update_rejects_order_integer(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $admin = User::factory()->admin()->create();
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['sort_order'] = 1.5;

        $response = $this->actingAs($admin)->putJson(route('admin.plans.update', $plan), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_invalid_browser_form_redirects_with_errors_and_old_input(): void
    {
        $plan = Plan::factory()->create();
        $admin = User::factory()->admin()->create();
        $url = route('admin.plans.edit', $plan);
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['name'] = '再表示する名前';
        $data['duration_days'] = 0;

        $response = $this->actingAs($admin)->from($url)->put(route('admin.plans.update', $plan), $data);

        $response->assertRedirect($url);
        $response->assertSessionHasErrors('duration_days');
        $response->assertSessionHasInput('name', '再表示する名前');
    }

    public function test_patch_is_not_accepted_for_update(): void
    {
        $plan = Plan::factory()->create();
        $response = $this->actingAs(User::factory()->admin()->create())->patchJson(route('admin.plans.update', $plan));
        $response->assertStatus(405);
    }
}
