<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Enrollment;
use App\Services\MeetingAvailabilityService;
use Carbon\Carbon;
use Illuminate\Support\Collection;

final class FetchAvailabilityAction
{
    /** @return Collection<int, array{slot_start: Carbon, slot_end: Carbon, available_coach_count: int}> */
    public function __invoke(Enrollment $enrollment, Carbon $date, MeetingAvailabilityService $service): Collection
    {
        return $service->slotsForCertification($enrollment->loadMissing('certification')->certification, $date);
    }
}
