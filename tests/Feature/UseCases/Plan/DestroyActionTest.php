<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanLog;
use App\UseCases\Plan\DestroyAction;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_stale_model_using_current_database_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['status' => 'draft']);
        Plan::whereKey($plan->id)->update(['status' => 'archived']);

        try {
            app(DestroyAction::class)($plan);
            $this->fail('最新状態では許可されない操作が成功しました。');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('archived', $plan->fresh()->status->value);
    }

    public function test_foreign_key_rejection_after_reference_check_becomes_conflict(): void
    {
        $plan = Plan::factory()->draft()->create();
        $user = User::factory()->create();
        $event = 'eloquent.deleting: '.Plan::class;
        // 参照チェックの後、DELETE直前に参照を追加して実DBの外部キー制約を検証する。
        // 同時実行そのものを再現するテストではない。
        Event::listen($event, function (Plan $deleting) use ($user) {
            UserPlanLog::factory()->create(['plan_id' => $deleting->id, 'user_id' => $user->id]);
        });

        try {
            app(DestroyAction::class)($plan);
            $this->fail('外部キーから参照されるプランが削除されました。');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
            $this->assertInstanceOf(QueryException::class, $exception->getPrevious());
            $this->assertSame(1451, $exception->getPrevious()->errorInfo[1]);
        } finally {
            Event::forget($event);
        }
        $this->assertModelExists($plan);
        $this->assertDatabaseCount('user_plan_logs', 0);
    }

    public function test_unrelated_database_errors_are_not_disguised_as_reference_conflicts(): void
    {
        $plan = Plan::factory()->draft()->create();
        $event = 'eloquent.deleting: '.Plan::class;
        Event::listen($event, function (Plan $deleting) {
            // NOT NULL違反は参照エラーとは異なるため、そのまま呼び出し元へ返す。
            Plan::whereKey($deleting->id)->update(['name' => null]);
        });

        try {
            app(DestroyAction::class)($plan);
            $this->fail('DBエラーが発生しませんでした。');
        } catch (QueryException $exception) {
            $this->assertSame(1048, $exception->errorInfo[1]);
        } finally {
            Event::forget($event);
        }
        $this->assertModelExists($plan);
    }
}
