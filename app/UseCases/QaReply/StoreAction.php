<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(User $user, QaThread $thread, string $body): QaReply
    {
        return DB::transaction(fn () => $thread->replies()->create(['user_id' => $user->id, 'body' => $body]));
    }
}
