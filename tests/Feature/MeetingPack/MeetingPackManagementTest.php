<?php

declare(strict_types=1);

namespace Tests\Feature\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use App\UseCases\MeetingPack\ArchiveAction;
use App\UseCases\MeetingPack\DestroyAction;
use App\UseCases\MeetingPack\PublishAction;
use App\UseCases\MeetingPack\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class MeetingPackManagementTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => '追加面談パック',
            'description' => null,
            'meeting_count' => 1,
            'price' => 0,
            'stripe_price_id' => null,
            'sort_order' => null,
        ], $overrides);
    }

    public function test_admin_can_render_all_four_existing_screens(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = MeetingPack::factory()->create(['created_by_user_id' => $admin->id, 'updated_by_user_id' => $admin->id]);
        $this->actingAs($admin);

        $this->get(route('admin.meeting-packs.index'))->assertOk()->assertSee($plan->name);
        $this->get(route('admin.meeting-packs.create'))->assertOk();
        $this->get(route('admin.meeting-packs.edit', $plan))->assertOk();
        $this->get(route('admin.meeting-packs.show', $plan))->assertOk()
            ->assertSee($admin->name)->assertSee('この SKU の購入はまだありません。')
            ->assertSee(route('admin.meeting-packs.publish', $plan), false)
            ->assertDontSee(route('admin.meeting-packs.archive', $plan), false);
    }

    public function test_index_filters_zero_keyword_and_status_and_keeps_pagination_query(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $older = MeetingPack::factory()->published()->create([
            'name' => '0 回ではないパック', 'sort_order' => 0, 'created_at' => now()->subDay(),
        ]);
        $newer = MeetingPack::factory()->published()->create(['name' => '0 を含む', 'sort_order' => 0]);
        MeetingPack::factory()->published()->count(19)->create(['name' => '0 を含む', 'sort_order' => 1]);
        MeetingPack::factory()->draft()->create(['name' => '0 を含む']);
        MeetingPack::factory()->published()->create(['name' => '対象外']);

        $this->get(route('admin.meeting-packs.index', ['keyword' => '0', 'status' => 'published']))
            ->assertOk()->assertViewHas('plans', function ($plans) use ($older, $newer) {
                $this->assertSame(21, $plans->total());
                $this->assertCount(20, $plans->items());
                $this->assertSame([$newer->id, $older->id], $plans->getCollection()->take(2)->pluck('id')->all());
                $this->assertStringContainsString('keyword=0', $plans->nextPageUrl());
                $this->assertStringContainsString('status=published', $plans->nextPageUrl());

                return true;
            });
        $this->getJson(route('admin.meeting-packs.index', ['status' => 'invalid']))
            ->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_store_ignores_forged_status_and_metadata_and_defaults_optional_fields(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->post(route('admin.meeting-packs.store'), $this->payload([
            'status' => 'published', 'created_by_user_id' => 'forged', 'updated_by_user_id' => 'forged',
        ]))->assertSessionHasNoErrors()->assertRedirect();

        $plan = MeetingPack::sole();
        $this->assertSame(MeetingPackStatus::Draft, $plan->status);
        $this->assertSame($admin->id, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertSame(0, $plan->price);
        $this->assertSame(0, $plan->sort_order);
    }

    public function test_published_pack_can_be_edited_without_changing_status_or_creator(): void
    {
        $plan = MeetingPack::factory()->published()->create();
        $creatorId = $plan->created_by_user_id;
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->patch(route('admin.meeting-packs.update', $plan), $this->payload([
            'name' => str_repeat('あ', 100), 'description' => str_repeat('あ', 2000),
            'meeting_count' => 100, 'price' => 1000000, 'stripe_price_id' => str_repeat('p', 255),
            'sort_order' => 100000, 'status' => 'draft', 'created_by_user_id' => $admin->id,
        ]))->assertSessionHasNoErrors()->assertRedirect(route('admin.meeting-packs.show', $plan));

        $plan->refresh();
        $this->assertSame(MeetingPackStatus::Published, $plan->status);
        $this->assertSame($creatorId, $plan->created_by_user_id);
        $this->assertSame($admin->id, $plan->updated_by_user_id);
        $this->assertSame(100, $plan->meeting_count);
        $this->assertSame(1000000, $plan->price);
        $this->assertSame(100000, $plan->sort_order);
    }

    public static function invalidInputs(): array
    {
        return [
            'name required' => ['name', ''],
            'name max' => ['name', str_repeat('あ', 101)],
            'description max' => ['description', str_repeat('あ', 2001)],
            'count min' => ['meeting_count', 0],
            'count max' => ['meeting_count', 101],
            'count integer' => ['meeting_count', 1.5],
            'price min' => ['price', -1],
            'price max' => ['price', 1000001],
            'price integer' => ['price', 1.5],
            'price id max' => ['stripe_price_id', str_repeat('x', 256)],
            'order min' => ['sort_order', -1],
            'order integer' => ['sort_order', 1.5],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_store_and_update_reject_invalid_input(string $field, mixed $value): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $plan = MeetingPack::factory()->create();
        $data = $this->payload([$field => $value]);
        $this->postJson(route('admin.meeting-packs.store'), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->patchJson(route('admin.meeting-packs.update', $plan), $data)
            ->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('meeting_packs', 1);
    }

    public function test_only_three_lifecycle_transitions_are_allowed_and_update_metadata(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin);
        $valid = ['draft' => 'publish', 'published' => 'archive', 'archived' => 'unarchive'];
        $targets = ['publish' => MeetingPackStatus::Published, 'archive' => MeetingPackStatus::Archived, 'unarchive' => MeetingPackStatus::Draft];

        foreach (MeetingPackStatus::cases() as $status) {
            foreach ($targets as $operation => $target) {
                $plan = MeetingPack::factory()->create(['status' => $status]);
                $response = $this->postJson(route('admin.meeting-packs.'.$operation, $plan));
                if ($valid[$status->value] === $operation) {
                    $response->assertRedirect();
                    $this->assertSame($target, $plan->fresh()->status);
                    $this->assertSame($admin->id, $plan->fresh()->updated_by_user_id);
                } else {
                    $response->assertForbidden();
                    $this->assertSame($status, $plan->fresh()->status);
                }
            }
        }
    }

    public function test_delete_is_physical_and_rejects_published_packs(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        foreach (MeetingPackStatus::cases() as $status) {
            $plan = MeetingPack::factory()->create(['status' => $status]);
            $response = $this->deleteJson(route('admin.meeting-packs.destroy', $plan));
            if ($status === MeetingPackStatus::Published) {
                $response->assertForbidden();
                $this->assertModelExists($plan);
            } else {
                $response->assertRedirect(route('admin.meeting-packs.index'));
                $this->assertModelMissing($plan);
            }
        }
    }

    public function test_actions_recheck_database_status_when_given_stale_models(): void
    {
        $admin = User::factory()->admin()->create();
        foreach ([
            [PublishAction::class, 'draft', 'archived'],
            [ArchiveAction::class, 'published', 'draft'],
            [UnarchiveAction::class, 'archived', 'published'],
            [DestroyAction::class, 'draft', 'published'],
        ] as [$action, $before, $after]) {
            $plan = MeetingPack::factory()->create(['status' => $before]);
            MeetingPack::whereKey($plan->id)->update(['status' => $after]);

            try {
                if ($action === DestroyAction::class) {
                    (new DestroyAction)($plan);
                } else {
                    (new $action)($plan, $admin);
                }
                $this->fail('最新状態に反する操作が許可されました。');
            } catch (ConflictHttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
            $this->assertSame($after, $plan->fresh()->status->value);
        }
    }

    public function test_student_and_coach_are_denied_all_ten_endpoints(): void
    {
        $plan = MeetingPack::factory()->create();
        foreach (['student', 'coach'] as $role) {
            $this->actingAs(User::factory()->{$role}()->create());
            foreach ([
                ['GET', 'index', []], ['GET', 'create', []], ['POST', 'store', []],
                ['GET', 'show', [$plan]], ['GET', 'edit', [$plan]], ['PATCH', 'update', [$plan]],
                ['DELETE', 'destroy', [$plan]], ['POST', 'publish', [$plan]],
                ['POST', 'archive', [$plan]], ['POST', 'unarchive', [$plan]],
            ] as [$method, $name, $parameters]) {
                $this->json($method, route('admin.meeting-packs.'.$name, $parameters), $this->payload())
                    ->assertForbidden();
            }
        }
        $this->assertDatabaseCount('meeting_packs', 1);
        $this->assertSame(MeetingPackStatus::Draft, $plan->fresh()->status);
    }

    public function test_guest_is_redirected_and_unknown_pack_is_not_found(): void
    {
        $this->get(route('admin.meeting-packs.index'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin/meeting-packs/01AAAAAAAAAAAAAAAAAAAAAAAA')->assertNotFound();
    }
}
