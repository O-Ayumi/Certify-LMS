<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DestroyAction
{
    public function __invoke(MeetingPack $plan): void
    {
        try {
            DB::transaction(function () use ($plan) {
                $locked = MeetingPack::query()->lockForUpdate()->findOrFail($plan->id);

                if ($locked->status === MeetingPackStatus::Published) {
                    throw new ConflictHttpException('公開中の面談パックは削除できません。');
                }

                // 後続の購入テーブルは、このマスタへの外部キーをRESTRICTで追加すること。
                // 購入履歴は状態を問わず保持し、CASCADE削除や参照のNULL化は行わない。
                $locked->delete();
            });
        } catch (QueryException $exception) {
            // MySQL: 子レコードが参照しているマスタの削除拒否だけを業務エラーに変換する。
            // その他のDBエラーを「購入済み」と誤って扱わない。
            if (($exception->errorInfo[1] ?? null) !== 1451) {
                throw $exception;
            }

            throw new ConflictHttpException(
                '購入履歴などの関連データがあるため、この面談パックは削除できません。',
                $exception,
            );
        }
    }
}
