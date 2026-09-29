<?php

declare(strict_types=1);

namespace App\UseCases\MeetingQuota;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Stripe\Event;

final class ProcessStripeWebhookAction
{
    public function __invoke(Event $event): void
    {
        $object = $event->data->object;
        $paymentId = (string) ($object->metadata->payment_id ?? '');
        $sessionId = (string) ($object->id ?? '');

        if ($paymentId === '' || $sessionId === '') {
            return;
        }

        DB::transaction(function () use ($event, $object, $paymentId, $sessionId): void {
            $payment = Payment::query()->whereKey($paymentId)->lockForUpdate()->first();
            if ($payment === null || ($payment->stripe_checkout_session_id !== null && $payment->stripe_checkout_session_id !== $sessionId)) {
                return;
            }

            if ($payment->stripe_checkout_session_id === null) {
                $payment->update(['stripe_checkout_session_id' => $sessionId]);
            }

            if (in_array($event->type, ['checkout.session.completed', 'checkout.session.async_payment_succeeded'], true)) {
                $this->completePayment($payment, $object);

                return;
            }

            if ($payment->status === PaymentStatus::Pending && $event->type === 'checkout.session.async_payment_failed') {
                $payment->update(['status' => PaymentStatus::Failed]);
            } elseif ($payment->status === PaymentStatus::Pending && $event->type === 'checkout.session.expired') {
                $payment->update(['status' => PaymentStatus::Failed]);
            }
        });
    }

    private function completePayment(Payment $payment, object $session): void
    {
        $paymentStatus = $session->payment_status ?? null;
        $isSuccessful = $paymentStatus === 'paid'
            || ($payment->amount === 0 && $paymentStatus === 'no_payment_required');

        if ($payment->status === PaymentStatus::Succeeded
            || ! $isSuccessful
            || ($session->currency ?? null) !== strtolower($payment->currency)
            || (int) ($session->amount_total ?? -1) !== $payment->amount
            || (string) ($session->client_reference_id ?? '') !== $payment->user_id) {
            return;
        }

        $paymentIntent = $session->payment_intent ?? null;
        $payment->update([
            'status' => PaymentStatus::Succeeded,
            'stripe_payment_intent_id' => is_string($paymentIntent) ? $paymentIntent : ($paymentIntent->id ?? null),
            'paid_at' => now(),
        ]);

        MeetingQuotaTransaction::query()->create([
            'user_id' => $payment->user_id,
            'type' => MeetingQuotaTransactionType::Purchased,
            'amount' => $payment->quantity,
            'related_payment_id' => $payment->id,
            'note' => $payment->meeting_pack_name_snapshot,
            'occurred_at' => now(),
        ]);
    }
}
