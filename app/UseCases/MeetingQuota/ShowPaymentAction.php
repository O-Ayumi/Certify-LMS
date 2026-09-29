<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Models\Payment;
use App\Models\User;

final class ShowPaymentAction
{
    public function __invoke(User $user, string $sessionId): ?Payment
    {
        return Payment::query()
            ->where('user_id', $user->id)
            ->where('stripe_checkout_session_id', $sessionId)
            ->first();
    }
}
