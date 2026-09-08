<?php

declare(strict_types=1);

namespace App\UseCases\Plan;

use App\Enums\PlanStatus;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用のプランマスタ一覧をフィルタ付きで取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = Plan::query();

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query
            ->withCount('users')
            ->orderByRaw(
                'CASE status WHEN ? THEN 0 WHEN ? THEN 1 WHEN ? THEN 2 ELSE 3 END',
                [
                    PlanStatus::Published->value,
                    PlanStatus::Draft->value,
                    PlanStatus::Archived->value,
                ],
            )
            ->ordered()
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();
    }
}
