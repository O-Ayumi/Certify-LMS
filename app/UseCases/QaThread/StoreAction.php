<?php

declare(strict_types=1);

namespace App\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(User $user, array $data): QaThread
    {
        return DB::transaction(fn () => QaThread::create([
            'certification_id' => $data['certification_id'],
            'user_id' => $user->id,
            'title' => $data['title'],
            'body' => $data['body'],
            'status' => QaThreadStatus::Open,
            'resolved_at' => null,
        ]));
    }
}
