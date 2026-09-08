<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Exceptions\Plan\PlanDeletionNotAllowedException;
use App\Models\Plan;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    /**
     * @throws PlanDeletionNotAllowedException
     */
    public function __invoke(Plan $plan): void
    {
        try {
            DB::transaction(function () use ($plan) {
                $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);

                if ($locked->status !== PlanStatus::Draft) {
                    throw PlanDeletionNotAllowedException::forNotDraft();
                }

                // 論理削除済みユーザーも含め、現在の紐づきとプラン履歴を保護する。
                if (
                    $locked->users()->withTrashed()->exists()
                    || $locked->userPlanLogs()->exists()
                ) {
                    throw PlanDeletionNotAllowedException::forReferenced();
                }

                $locked->delete();
            });
        } catch (QueryException $exception) {
            // MySQL: 子レコードが参照しているマスタの削除拒否だけを業務エラーに変換する。
            // その他のDBエラーを「参照あり」と誤って扱わない。
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            throw PlanDeletionNotAllowedException::forReferenced($exception);
        }
    }
}
