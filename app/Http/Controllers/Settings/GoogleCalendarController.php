<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\GoogleCalendarService;
use App\UseCases\GoogleCalendar\CallbackAction;
use App\UseCases\GoogleCalendar\ConnectAction;
use App\UseCases\GoogleCalendar\DisconnectAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class GoogleCalendarController extends Controller
{
    public function redirect(Request $request, GoogleCalendarService $google, ConnectAction $action): RedirectResponse
    {
        $state = Str::random(64);
        $request->session()->put('google_calendar_oauth_state', $state);

        return redirect()->away($action($google, $state));
    }

    public function callback(Request $request, GoogleCalendarService $google, CallbackAction $action): RedirectResponse
    {
        abort_unless(hash_equals((string) $request->session()->pull('google_calendar_oauth_state'), (string) $request->query('state')), 403);
        if (! $request->filled('code')) {
            return redirect()->route('settings.availability.index')->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }
        try {
            $action($request->user(), $google, (string) $request->query('code'));
        } catch (\Throwable $exception) {
            Log::warning('Google Calendar OAuth callback failed.', [
                'user_id' => $request->user()->id,
                'exception' => $exception::class,
            ]);

            return redirect()->route('settings.availability.index')->with('error', 'Googleカレンダーとの連携に失敗しました。');
        }

        return redirect()->route('settings.availability.index')->with('success', 'Googleカレンダーと連携しました。');
    }

    public function destroy(Request $request, DisconnectAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()->route('settings.availability.index')->with('success', 'Googleカレンダーの連携を解除しました。');
    }
}
