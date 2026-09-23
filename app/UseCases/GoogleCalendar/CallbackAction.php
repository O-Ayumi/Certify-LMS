<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;

final class CallbackAction
{
    public function __invoke(User $user, GoogleCalendarService $google, string $code): void
    {
        [$googleUserId, $token] = $google->connect($code);
        $credential = $user->googleCredential;

        GoogleCalendarCredential::updateOrCreate(
            ['user_id' => $user->id],
            [
                'google_user_id' => $googleUserId,
                'calendar_id' => 'primary',
                'access_token' => json_encode($token),
                'refresh_token' => $token['refresh_token'] ?? $credential?->refresh_token,
                'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600)),
                'connected_at' => now(),
            ],
        );
    }
}
