<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\User;
use Illuminate\Support\Facades\DB;

final class DisconnectAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(fn () => $user->googleCredential()?->delete());
    }
}
