<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanInvalidTransitionException;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class UnarchiveAction
{
    /**
     * @throws PlanInvalidTransitionException
     */
    public function __invoke(Plan $plan, User $auth): Plan
    {
        return DB::transaction(function () use ($plan, $auth) {
            // 別の管理者による変更と競合しないよう、ロックして最新状態を確認する。
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);

            if ($locked->status !== PlanStatus::Archived) {
                throw PlanInvalidTransitionException::forUnarchive();
            }

            $locked->update([
                'status' => PlanStatus::Draft,
                'updated_by_user_id' => $auth->id,
            ]);

            return $locked;
        });
    }
}
