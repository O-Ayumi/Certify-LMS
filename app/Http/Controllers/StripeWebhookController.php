<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\StripeCheckoutService;
use App\UseCases\MeetingQuota\ProcessStripeWebhookAction;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Stripe\Exception\SignatureVerificationException;
use UnexpectedValueException;

class StripeWebhookController extends Controller
{
    public function __invoke(
        Request $request,
        StripeCheckoutService $stripe,
        ProcessStripeWebhookAction $processWebhook,
    ): Response {
        try {
            $event = $stripe->constructWebhookEvent(
                $request->getContent(),
                (string) $request->header('Stripe-Signature'),
            );
        } catch (SignatureVerificationException|UnexpectedValueException) {
            return response('Invalid Stripe webhook', 400);
        }

        $processWebhook($event);

        return response('', 200);
    }
}
