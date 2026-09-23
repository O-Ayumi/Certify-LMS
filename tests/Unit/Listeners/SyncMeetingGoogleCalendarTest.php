<?php

declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\MeetingCanceled;
use App\Events\MeetingReserved;
use App\Listeners\SyncMeetingGoogleCalendar;
use App\Models\GoogleCalendarCredential;
use App\Models\Meeting;
use App\Models\User;
use App\Services\GoogleCalendarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

final class SyncMeetingGoogleCalendarTest extends TestCase
{
    use RefreshDatabase;

    public function test_reserved_meeting_stores_google_event_id(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach)->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->create();
        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('createEvent')->once()->andReturn('google-event-id');

        (new SyncMeetingGoogleCalendar($google))->handle(new MeetingReserved($meeting));

        $this->assertSame('google-event-id', $meeting->fresh()->google_event_id);
    }

    public function test_google_event_creation_failure_does_not_fail_lms_meeting(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach)->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->create();
        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('createEvent')->once()->andReturnNull();

        (new SyncMeetingGoogleCalendar($google))->handle(new MeetingReserved($meeting));

        $this->assertDatabaseHas('meetings', ['id' => $meeting->id, 'status' => 'reserved']);
        $this->assertNull($meeting->fresh()->google_event_id);
    }

    public function test_canceled_meeting_deletes_saved_google_event(): void
    {
        $coach = User::factory()->coach()->create();
        GoogleCalendarCredential::factory()->for($coach)->create();
        $meeting = Meeting::factory()->canceled()->forCoach($coach)->create(['google_event_id' => 'google-event-id']);
        $google = Mockery::mock(GoogleCalendarService::class);
        $google->shouldReceive('deleteEvent')->once();

        (new SyncMeetingGoogleCalendar($google))->handle(new MeetingCanceled($meeting));

        $this->assertSame('google-event-id', $meeting->fresh()->google_event_id);
    }
}
