<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Plan;

use App\Enums\UserStatus;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_filters_name_and_status_and_keeps_filters_on_the_second_page(): void
    {
        $admin = User::factory()->admin()->create();
        Plan::factory()->published()->count(21)->create(['name' => '検索0対象プラン']);
        Plan::factory()->draft()->create(['name' => '検索0対象プラン']);
        Plan::factory()->published()->create(['name' => '検索対象外']);
        $filters = ['keyword' => '0', 'status' => 'published'];
        $this->actingAs($admin);

        $response = $this->get(route('admin.plans.index', $filters));

        $response->assertOk();
        $response->assertViewHas('plans', function ($plans) {
            $this->assertSame(21, $plans->total());
            $this->assertCount(20, $plans->items());
            $this->assertStringContainsString('keyword=0', $plans->nextPageUrl());
            $this->assertStringContainsString('status=published', $plans->nextPageUrl());

            return true;
        });
        $response->assertDontSee('検索対象外');

        $response = $this->get(route('admin.plans.index', $filters + ['page' => 2]));

        $response->assertOk();
        $response->assertViewHas('plans', function ($plans) {
            $this->assertSame(21, $plans->total());
            $this->assertSame(2, $plans->currentPage());
            $this->assertCount(1, $plans->items());

            return true;
        });
    }

    public function test_index_orders_by_status_then_sort_order_then_newest_creation(): void
    {
        $admin = User::factory()->admin()->create();
        // 作成順や並び順だけでは期待する順序にならないデータを用意する。
        $archived = Plan::factory()->archived()->create(['sort_order' => 0]);
        $draft = Plan::factory()->draft()->create(['sort_order' => 0]);
        $laterOrder = Plan::factory()->published()->create(['sort_order' => 2]);
        $older = Plan::factory()->published()->create(['sort_order' => 1, 'created_at' => '2026-01-01']);
        $newer = Plan::factory()->published()->create(['sort_order' => 1, 'created_at' => '2026-01-02']);

        $response = $this->actingAs($admin)->get(route('admin.plans.index'));

        $response->assertOk();
        $response->assertViewHas('plans', function ($plans) use ($newer, $older, $laterOrder, $draft, $archived) {
            $this->assertSame(
                [$newer->id, $older->id, $laterOrder->id, $draft->id, $archived->id],
                $plans->getCollection()->pluck('id')->all(),
            );

            return true;
        });
    }

    public function test_index_counts_currently_linked_users_in_all_statuses_except_soft_deleted_users(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create();
        $expectedIds = [];
        foreach (UserStatus::cases() as $status) {
            $user = User::factory()->student()->withPlan($plan)->create([
                'status' => $status,
                'email' => $status->value.'@example.test',
                'plan_expires_at' => '2027-02-03 00:00:00',
                'max_meetings' => 17,
            ]);
            $expectedIds[] = $user->id;
        }
        $deleted = User::factory()->student()->withPlan($plan)->create();
        $deleted->delete();
        User::factory()->student()->withPlan(Plan::factory()->create())->create();
        $this->actingAs($admin);

        $response = $this->get(route('admin.plans.index'));

        $response->assertOk();
        $plans = $response->viewData('plans');
        $this->assertSame(count($expectedIds), $plans->getCollection()->firstWhere('id', $plan->id)->users_count);
    }

    public function test_index_shows_empty_state_when_no_plan_matches(): void
    {
        Plan::factory()->create(['name' => '既存プラン']);

        $response = $this->actingAs(User::factory()->admin()->create())
            ->get(route('admin.plans.index', ['keyword' => '一致しない検索語']));

        $response->assertOk();
        $response->assertSee('該当するプランがありません');
        $response->assertViewHas('plans', fn ($plans) => $plans->total() === 0);
    }

    public function test_student_cannot_index_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->student()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.index'));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_coach_cannot_index_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();
        $user = User::factory()->coach()->create();
        $this->actingAs($user);

        $response = $this->get(route('admin.plans.index'));

        $response->assertForbidden();
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_guest_cannot_index_plans(): void
    {
        $plan = Plan::factory()->create();
        $before = $plan->fresh()->getRawOriginal();

        $response = $this->get(route('admin.plans.index'));

        $response->assertRedirect(route('login'));
        $this->assertSame($before, $plan->fresh()->getRawOriginal());
        $this->assertDatabaseCount('plans', 1);
    }

    public function test_index_rejects_keyword_length(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.plans.index', ['keyword' => str_repeat('あ', 101)]));
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('keyword');
    }

    public function test_index_rejects_keyword_type(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.plans.index', ['keyword' => ['x']]));
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('keyword');
    }

    public function test_index_rejects_status_value(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.plans.index', ['status' => 'invalid']));
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }

    public function test_index_rejects_status_type(): void
    {
        $response = $this->actingAs(User::factory()->admin()->create())->getJson(route('admin.plans.index', ['status' => ['draft']]));
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('status');
    }
}
