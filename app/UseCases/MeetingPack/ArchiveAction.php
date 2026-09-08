<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ArchiveAction
{
    public function __invoke(MeetingPack $plan, User $auth): MeetingPack
    {
        return DB::transaction(function () use ($plan, $auth) {
            // Policy判定後に別の管理者が変更した場合も、最新状態で再判定する。
            $locked = MeetingPack::query()->lockForUpdate()->findOrFail($plan->id);

            if ($locked->status !== MeetingPackStatus::Published) {
                throw new ConflictHttpException('公開中の面談パックのみアーカイブできます。');
            }

            $locked->update([
                'status' => MeetingPackStatus::Archived,
                'updated_by_user_id' => $auth->id,
            ]);

            return $locked;
        });
    }
}
