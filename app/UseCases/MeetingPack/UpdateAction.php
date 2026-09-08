<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/** 基本情報のみ更新する。状態と作成者は変更しない。 */
final class UpdateAction
{
    /** @param array<string, mixed> $validated */
    public function __invoke(MeetingPack $plan, User $auth, array $validated): MeetingPack
    {
        return DB::transaction(function () use ($plan, $auth, $validated) {
            $locked = MeetingPack::query()->lockForUpdate()->findOrFail($plan->id);
            $locked->update([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'meeting_count' => $validated['meeting_count'],
                'price' => $validated['price'],
                'stripe_price_id' => $validated['stripe_price_id'] ?? null,
                'sort_order' => $validated['sort_order'] ?? 0,
                'updated_by_user_id' => $auth->id,
            ]);

            return $locked;
        });
    }
}
