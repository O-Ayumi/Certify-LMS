<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Models\MeetingPack;
use Illuminate\Database\Eloquent\Collection;

final class IndexPublishedPacksAction
{
    /** @return Collection<int, MeetingPack> */
    public function __invoke(): Collection
    {
        return MeetingPack::query()->published()->ordered()->get();
    }
}
