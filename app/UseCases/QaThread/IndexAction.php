<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class IndexAction
{
    public function __invoke(User $user, array $filters): LengthAwarePaginator
    {
        return QaThread::query()
            ->visibleTo($user)
            ->with(['certification', 'user'])
            ->withCount('replies')
            ->forCertification($filters['certification_id'] ?? null)
            ->forStatus($filters['status'] ?? null)
            ->keyword($filters['keyword'] ?? null)
            ->newest()
            ->paginate(20)
            ->withQueryString();
    }
}
