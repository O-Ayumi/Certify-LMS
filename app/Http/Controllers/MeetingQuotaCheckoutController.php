<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\MeetingQuota\CheckoutStoreRequest;
use App\UseCases\MeetingQuota\CreateCheckoutSessionAction;
use App\UseCases\MeetingQuota\IndexPublishedPacksAction;
use App\UseCases\MeetingQuota\ShowPaymentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Stripe\Exception\ApiErrorException;

class MeetingQuotaCheckoutController extends Controller
{
    public function index(IndexPublishedPacksAction $action): View
    {
        return view('meeting-quota.checkout-select', ['plans' => $action()]);
    }

    public function store(
        CheckoutStoreRequest $request,
        CreateCheckoutSessionAction $action,
    ): RedirectResponse {
        try {
            $session = $action($request->user(), $request->validated('meeting_pack_id'));
        } catch (ApiErrorException $exception) {
            report($exception);

            return back()->withErrors(['checkout' => '決済画面を開始できませんでした。時間をおいて再度お試しください。']);
        }

        return redirect()->away($session->url);
    }

    public function success(
        Request $request,
        ShowPaymentAction $action,
    ): View {
        return view('meeting-quota.success', [
            'payment' => $action($request->user(), (string) $request->query('session_id')),
        ]);
    }
}
