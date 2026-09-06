<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\UserRole;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class DestroyAction
{
    public function __invoke(QaThread $thread, User $actor): void
    {
        DB::transaction(function () use ($thread, $actor): void {
            $locked = QaThread::query()->lockForUpdate()->findOrFail($thread->id);
            if ($actor->role !== UserRole::Admin && $locked->replies()->exists()) {
                throw new AuthorizationException('回答が付いている質問は削除できません。');
            }
            $locked->delete();
        });
    }
}
