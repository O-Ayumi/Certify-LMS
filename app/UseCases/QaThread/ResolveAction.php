<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use Illuminate\Support\Facades\DB;

final class ResolveAction
{
    public function __invoke(QaThread $thread): QaThread
    {
        if ($thread->status === QaThreadStatus::Resolved) {
            return $thread;
        }

        return DB::transaction(function () use ($thread) {
            $thread->update(['status' => QaThreadStatus::Resolved, 'resolved_at' => now()]);

            return $thread->fresh();
        });
    }
}
