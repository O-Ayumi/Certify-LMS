<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GoogleCalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_unconnected_coach_is_never_busy(): void
    {
        $coach = User::factory()->coach()->create();

        $this->assertFalse(app(GoogleCalendarService::class)->isBusy($coach, Carbon::parse('2026-06-01 10:00')));
    }

    public function test_credential_factory_uses_primary_calendar(): void
    {
        $coach = User::factory()->coach()->create();
        $credential = GoogleCalendarCredential::factory()->for($coach)->create();

        $this->assertSame('primary', $credential->calendar_id);
        $this->assertSame($coach->id, $credential->user_id);
    }
}
