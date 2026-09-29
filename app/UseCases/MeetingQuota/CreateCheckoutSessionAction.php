<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingPackStatus;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use App\Services\StripeCheckoutService;
use Stripe\Checkout\Session;

final class CreateCheckoutSessionAction
{
    public function __construct(private readonly StripeCheckoutService $stripe) {}

    public function __invoke(User $user, string $meetingPackId): Session
    {
        $pack = MeetingPack::query()
            ->whereKey($meetingPackId)
            ->where('status', MeetingPackStatus::Published)
            ->firstOrFail();

        $payment = Payment::query()->create([
            'user_id' => $user->id,
            'meeting_pack_id' => $pack->id,
            'meeting_pack_name_snapshot' => $pack->name,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
            'currency' => 'JPY',
            'status' => PaymentStatus::Pending,
        ]);

        try {
            $session = $this->stripe->createCheckoutSession($payment);
        } catch (\Throwable $exception) {
            $payment->update(['status' => PaymentStatus::Failed]);
            throw $exception;
        }

        $payment->update(['stripe_checkout_session_id' => $session->id]);

        return $session;
    }
}
