<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Meeting;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** 呼び出し元の DB::afterCommit() から発火する業務イベント。 */
final class MeetingCanceled
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Meeting $meeting) {}
}
