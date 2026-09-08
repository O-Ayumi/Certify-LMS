<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Enums\MeetingPackStatus;
use App\Models\MeetingPack;
use App\Models\User;

/** 新規作成は必ず下書き。作成者・更新者はログイン中の管理者を記録する。 */
final class StoreAction
{
    /** @param array<string, mixed> $validated */
    public function __invoke(User $auth, array $validated): MeetingPack
    {
        return MeetingPack::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'meeting_count' => $validated['meeting_count'],
            'price' => $validated['price'],
            'stripe_price_id' => $validated['stripe_price_id'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
            'status' => MeetingPackStatus::Draft,
            'created_by_user_id' => $auth->id,
            'updated_by_user_id' => $auth->id,
        ]);
    }
}
