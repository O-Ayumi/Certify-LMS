<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use Carbon\Carbon;
use Google\Client;
use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Google\Service\Calendar\FreeBusyRequest;
use Google\Service\Calendar\FreeBusyRequestItem;
use Google\Service\Oauth2;
use Illuminate\Support\Facades\Log;
use Throwable;

class GoogleCalendarService
{
    public function client(?GoogleCalendarCredential $credential = null): Client
    {
        $client = new Client;
        $client->setClientId((string) config('services.google.client_id'));
        $client->setClientSecret((string) config('services.google.client_secret'));
        $client->setRedirectUri((string) config('services.google.redirect_uri'));
        $client->setScopes([
            Calendar::CALENDAR_FREEBUSY,
            Calendar::CALENDAR_EVENTS,
            'openid',
            'email',
            'profile',
        ]);
        $client->setAccessType('offline');
        $client->setPrompt('consent');
        if ($credential) {
            $token = json_decode($credential->access_token, true) ?: ['access_token' => $credential->access_token];
            if ($credential->refresh_token) {
                $token['refresh_token'] = $credential->refresh_token;
            }
            $client->setAccessToken($token);
            if ($client->isAccessTokenExpired() && $credential->refresh_token) {
                $token = $client->fetchAccessTokenWithRefreshToken($credential->refresh_token);
                if (isset($token['access_token'])) {
                    $credential->update(['access_token' => json_encode($token), 'expires_at' => now()->addSeconds((int) ($token['expires_in'] ?? 3600))]);
                    $client->setAccessToken($token);
                }
            }
        }

        return $client;
    }

    public function authorizationUrl(string $state): string
    {
        $client = $this->client();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function connect(string $code): array
    {
        $client = $this->client();
        $token = $client->fetchAccessTokenWithAuthCode($code);
        if (isset($token['error'])) {
            throw new \RuntimeException('Google OAuth failed.');
        }
        $client->setAccessToken($token);
        $oauth = new Oauth2($client);
        $info = $oauth->userinfo->get();

        return [$info->getId(), $token];
    }

    public function createEvent(GoogleCalendarCredential $credential, Meeting $meeting): ?string
    {
        try {
            $calendar = new Calendar($this->client($credential));
            $start = $meeting->scheduled_at;
            $event = $calendar->events->insert($credential->calendar_id, new Event([
                'summary' => '面談', 'description' => $meeting->topic,
                'location' => $meeting->meeting_url_snapshot,
                'start' => new EventDateTime(['dateTime' => $start->toRfc3339String(), 'timeZone' => config('app.timezone')]),
                'end' => new EventDateTime(['dateTime' => $start->copy()->addHour()->toRfc3339String(), 'timeZone' => config('app.timezone')]),
            ]));

            return $event->getId();
        } catch (Throwable $e) {
            Log::warning('Google Calendar event creation failed.', ['meeting_id' => $meeting->id]);

            return null;
        }
    }

    public function deleteEvent(GoogleCalendarCredential $credential, Meeting $meeting): void
    {
        if (! $meeting->google_event_id) {
            return;
        }
        try {
            (new Calendar($this->client($credential)))->events->delete($credential->calendar_id, $meeting->google_event_id);
        } catch (Throwable $e) {
            Log::warning('Google Calendar event deletion failed.', ['meeting_id' => $meeting->id]);
        }
    }

    /** @return array<int, array{start: Carbon, end: Carbon}> */
    public function busyPeriods(User $coach, Carbon $date): array
    {
        $credential = $coach->googleCredential;
        if (! $credential) {
            return [];
        }
        try {
            $calendar = new Calendar($this->client($credential));
            $request = new FreeBusyRequest([
                'timeMin' => $date->copy()->startOfDay()->toRfc3339String(),
                'timeMax' => $date->copy()->endOfDay()->toRfc3339String(),
                'items' => [new FreeBusyRequestItem(['id' => $credential->calendar_id])],
            ]);
            $response = $calendar->freebusy->query($request);

            return collect($response->getCalendars()[$credential->calendar_id]->getBusy() ?? [])->map(fn ($period) => [
                'start' => Carbon::parse($period->getStart()), 'end' => Carbon::parse($period->getEnd()),
            ])->all();
        } catch (Throwable $e) {
            Log::warning('Google Calendar free/busy lookup failed.', ['coach_id' => $coach->id]);

            return [];
        }
    }

    public function isBusy(User $coach, Carbon $start): bool
    {
        $end = $start->copy()->addHour();

        return collect($this->busyPeriods($coach, $start))->contains(fn (array $period) => $start->lt($period['end']) && $end->gt($period['start'])
        );
    }
}
