<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        // 購入データの実連携は後続チケット。現時点は既存画面の空表示を利用する。
        return $plan->load(['createdBy', 'updatedBy']);
    }
}
