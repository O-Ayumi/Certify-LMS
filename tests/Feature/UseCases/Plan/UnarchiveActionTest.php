<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use App\UseCases\Plan\UnarchiveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Tests\TestCase;

class UnarchiveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_stale_model_using_current_database_status(): void
    {
        $admin = User::factory()->admin()->create();
        $plan = Plan::factory()->create(['status' => 'archived']);
        Plan::whereKey($plan->id)->update(['status' => 'draft']);

        try {
            app(UnarchiveAction::class)($plan, $admin);
            $this->fail('最新状態では許可されない操作が成功しました。');
        } catch (ConflictHttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame('draft', $plan->fresh()->status->value);
    }
}
