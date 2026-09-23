<?php

declare(strict_types=1);

namespace App\UseCases\GoogleCalendar;

use App\Services\GoogleCalendarService;

final class ConnectAction
{
    public function __invoke(GoogleCalendarService $google, string $state): string
    {
        return $google->authorizationUrl($state);
    }
}
