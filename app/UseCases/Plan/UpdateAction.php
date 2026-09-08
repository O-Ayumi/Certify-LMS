<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** 基本情報のみ更新する。状態と作成者は変更しない。 */
final class UpdateAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int|string, default_meeting_quota: int|string, sort_order?: int|string|null} $validated Plan/UpdateRequestで検証済みの入力
     */
    public function __invoke(Plan $plan, User $auth, array $validated): Plan
    {
        return DB::transaction(function () use ($plan, $auth, $validated) {
            $locked = Plan::query()->lockForUpdate()->findOrFail($plan->id);
            $locked->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'duration_days' => $validated['duration_days'],
                'default_meeting_quota' => $validated['default_meeting_quota'],
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by_user_id' => $auth->id,
            ]);

            return $locked;
        });
    }
}
