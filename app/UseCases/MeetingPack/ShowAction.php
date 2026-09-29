<?php

declare(strict_types=1);

namespace App\UseCases\MeetingPack;

use App\Models\MeetingPack;

final class ShowAction
{
    public function __invoke(MeetingPack $plan): MeetingPack
    {
        return $plan->loadCount('payments')->load([
            'createdBy',
            'updatedBy',
            'payments' => fn ($query) => $query->with('user')->latest()->limit(20),
        ]);
    }
}
