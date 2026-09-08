<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use App\Models\User;

/** 新規作成は必ず下書き。作成者・更新者はログイン中の管理者を記録する。 */
final class StoreAction
{
    /**
     * @param array{name: string, description?: ?string, duration_days: int|string, default_meeting_quota: int|string, sort_order?: int|string|null} $validated Plan/StoreRequestで検証済みの入力
     */
    public function __invoke(User $auth, array $validated): Plan
    {
        return Plan::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'duration_days' => $validated['duration_days'],
            'default_meeting_quota' => $validated['default_meeting_quota'],
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => PlanStatus::Draft,
            'created_by_user_id' => $auth->id,
            'updated_by_user_id' => $auth->id,
        ]);
    }
}
