<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * admin 用の面談パックマスタ一覧をフィルタ付きで取得するユースケース。
 */
final class IndexAction
{
    public function __invoke(
        ?string $keyword = null,
        ?string $status = null,
    ): LengthAwarePaginator {
        $query = MeetingPack::query();

        if ($keyword !== null && $keyword !== '') {
            $query->where('name', 'like', "%{$keyword}%");
        }

        if ($status !== null && $status !== '') {
            $query->where('status', $status);
        }

        return $query
            ->ordered()
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();
    }
}
