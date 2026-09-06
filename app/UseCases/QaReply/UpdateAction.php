<?php

declare(strict_types=1);

namespace App\UseCases\QaReply;

use App\Models\QaReply;
use Illuminate\Support\Facades\DB;

final class UpdateAction
{
    public function __invoke(QaReply $reply, string $body): QaReply
    {
        return DB::transaction(function () use ($reply, $body) {
            $reply->update(['body' => $body]);

            return $reply->fresh();
        });
    }
}
