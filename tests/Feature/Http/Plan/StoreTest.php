<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_cannot_store_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.store'), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_store_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->post(route('admin.plans.store'), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_store_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->post(route('admin.plans.store'), ['name' => '変更', 'duration_days' => 30, 'default_meeting_quota' => 0]);

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_store_defaults_omitted_optional_fields_and_ignores_forged_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $data = ['name' => '検証用プラン', 'duration_days' => 1, 'default_meeting_quota' => 0];
        $data += ['status' => 'published', 'created_by_user_id' => $other->id, 'updated_by_user_id' => $other->id];

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $data);

        $response->assertSessionHasNoErrors();
        $plan = Plan::sole();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertSame(1, $plan->duration_days);
        $this->assertSame(0, $plan->default_meeting_quota);
        $this->assertSame(0, $plan->sort_order);
        $this->assertNull($plan->description);
    }

    public function test_store_defaults_empty_optional_fields_and_ignores_forged_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $data = ['name' => '検証用プラン', 'duration_days' => 1, 'default_meeting_quota' => 0, 'description' => '', 'sort_order' => ''];
        $data += ['status' => 'published', 'created_by_user_id' => $other->id, 'updated_by_user_id' => $other->id];

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $data);

        $response->assertSessionHasNoErrors();
        $plan = Plan::sole();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertSame(1, $plan->duration_days);
        $this->assertSame(0, $plan->default_meeting_quota);
        $this->assertSame(0, $plan->sort_order);
        $this->assertNull($plan->description);
    }

    public function test_store_defaults_null_optional_fields_and_ignores_forged_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $other = User::factory()->admin()->create();
        $data = ['name' => '検証用プラン', 'duration_days' => 1, 'default_meeting_quota' => 0, 'description' => null, 'sort_order' => null];
        $data += ['status' => 'published', 'created_by_user_id' => $other->id, 'updated_by_user_id' => $other->id];

        $response = $this->actingAs($admin)->post(route('admin.plans.store'), $data);

        $response->assertSessionHasNoErrors();
        $plan = Plan::sole();
        $response->assertRedirect(route('admin.plans.show', $plan));
        $response->assertSessionHas('success');
        $this->assertSame(PlanStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertSame(1, $plan->duration_days);
        $this->assertSame(0, $plan->default_meeting_quota);
        $this->assertSame(0, $plan->sort_order);
        $this->assertNull($plan->description);
    }

    public function test_store_allows_duplicate_names(): void
    {
        Plan::factory()->create(['name' => '検証用プラン']);
        $response = $this->actingAs(User::factory()->admin()->create())->post(route('admin.plans.store'), ['name' => '検証用プラン', 'description' => null, 'duration_days' => 1, 'default_meeting_quota' => 0, 'sort_order' => null]);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertSame(2, Plan::where('name', '検証用プラン')->count());
    }

    public function test_store_accepts_maximum_values(): void
    {
        $data = [
            'name' => str_repeat('あ', 100), 'description' => str_repeat('説', 2000),
            'duration_days' => 3650, 'default_meeting_quota' => 1000, 'sort_order' => 4294967295,
        ];
        $response = $this->actingAs(User::factory()->admin()->create())->post(route('admin.plans.store'), $data);
        $response->assertSessionHasNoErrors();
        $response->assertRedirect();
        $this->assertDatabaseHas('plans', $data);
    }

    public function test_store_rejects_name_required(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_name_length(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_name_type(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_description_length(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_description_type(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('description');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_duration_required(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_duration_min(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_duration_max(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_duration_integer(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('duration_days');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_quota_required(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_quota_min(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_quota_max(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_quota_integer(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('default_meeting_quota');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_order_min(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_order_max(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_store_rejects_order_integer(): void
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

        $response = $this->actingAs($admin)->postJson(route('admin.plans.store'), $data);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('sort_order');
        $this->assertDatabaseCount('plans', 1);
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
    }

    public function test_invalid_browser_form_redirects_with_errors_and_old_input(): void
    {
        $plan = Plan::factory()->create();
        $admin = User::factory()->admin()->create();
        $url = route('admin.plans.create');
        $data = [
            'name' => '検証用プラン',
            'description' => null,
            'duration_days' => 1,
            'default_meeting_quota' => 0,
            'sort_order' => null,
        ];
        $data['name'] = '再表示する名前';
        $data['duration_days'] = 0;

        $response = $this->actingAs($admin)->from($url)->post(route('admin.plans.store'), $data);

        $response->assertRedirect($url);
        $response->assertSessionHasErrors('duration_days');
        $response->assertSessionHasInput('name', '再表示する名前');
    }
}
