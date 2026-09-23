<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingsProfile;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class GoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_coach_can_start_google_calendar_connection(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student)->get(route('settings.google-calendar.redirect'))->assertForbidden();
    }

    public function test_callback_rejects_invalid_state(): void
    {
        $coach = User::factory()->coach()->create();

        $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'expected'])
            ->get(route('settings.google-calendar.callback', ['state' => 'invalid', 'code' => 'code']))
            ->assertForbidden();
    }

    public function test_callback_stores_credential_for_authenticated_coach(): void
    {
        $coach = User::factory()->coach()->create();
        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('connect')->once()->with('code')->andReturn(['google-user', [
            'access_token' => 'access-token', 'refresh_token' => 'refresh-token', 'expires_in' => 3600,
        ]]);
        $this->app->instance(GoogleCalendarService::class, $google);

        $this->actingAs($coach)
            ->withSession(['google_calendar_oauth_state' => 'state'])
            ->get(route('settings.google-calendar.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseHas('google_calendar_credentials', [
            'user_id' => $coach->id, 'google_user_id' => 'google-user', 'calendar_id' => 'primary',
        ]);
    }

    public function test_coach_can_disconnect_without_deleting_lms_meetings(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach)->create();

        $this->actingAs($coach)->delete(route('settings.google-calendar.destroy'))
            ->assertRedirect(route('settings.availability.index'));

        $this->assertDatabaseMissing('google_calendar_credentials', ['user_id' => $coach->id]);
    }
}
