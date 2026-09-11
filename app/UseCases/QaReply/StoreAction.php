<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Events\QaReplyPosted;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class StoreAction
{
    public function __invoke(User $user, QaThread $thread, string $body): QaReply
    {
        return DB::transaction(function () use ($user, $thread, $body) {
            $reply = $thread->replies()->create(['user_id' => $user->id, 'body' => $body]);
            DB::afterCommit(fn () => QaReplyPosted::dispatch($reply));

            return $reply;
        });
    }
}
