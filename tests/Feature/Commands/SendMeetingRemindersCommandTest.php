<?php

declare(strict_types=1);

namespace Tests\Feature\Commands;

use App\Enums\MeetingStatus;
use App\Enums\UserStatus;
use App\Models\Meeting;
use App\Models\User;
use App\Notifications\Meeting\MeetingReminderNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Support\Str;
use Tests\TestCase;

class SendMeetingRemindersCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_eve_sends_to_both_in_progress_parties_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00'));
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $meeting = Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);
        $this->mock(MailChannel::class)->shouldReceive('send')->twice();

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertExitCode(0);
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertExitCode(0);

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(1, $coach->notifications()->count());
        $this->assertDatabaseCount('notifications', 2);
        $this->assertSame('eve', $student->notifications()->sole()->data['window']);
        $this->assertSame($meeting->id, $student->notifications()->sole()->data['meeting_id']);
    }

    public function test_one_hour_before_uses_the_55_to_65_minute_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $inWindow = Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addMinutes(60),
        ]);
        Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addMinutes(54),
        ]);
        Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addMinutes(66),
        ]);
        $this->mock(MailChannel::class)->shouldReceive('send')->twice();

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->assertExitCode(0);

        $this->assertSame(1, $student->notifications()->count());
        $this->assertSame(1, $coach->notifications()->count());
        $this->assertSame($inWindow->id, $student->notifications()->sole()->data['meeting_id']);
        $this->assertSame('one_hour_before', $student->notifications()->sole()->data['window']);
    }

    public function test_multiple_meetings_and_existing_other_notifications_are_handled_independently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 10:00:00'));
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $first = Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addMinutes(60),
        ]);
        $second = Meeting::factory()->forStudent($student)->forCoach($coach)->create([
            'scheduled_at' => now()->addMinutes(65),
        ]);
        $existing = new MeetingReservedNotification($first);
        $student->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => $existing::class,
            'data' => $existing->toArray($student),
        ]);
        $this->mock(MailChannel::class)->shouldReceive('send')->times(4);

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'one_hour_before'])
            ->assertExitCode(0);

        $this->assertSame(3, $student->notifications()->count());
        $this->assertSame(2, $coach->notifications()->count());
        $this->assertSame(2, $student->notifications()->where('type', MeetingReminderNotification::class)->count());
        $this->assertSame(2, $coach->notifications()->where('type', MeetingReminderNotification::class)->count());
        $this->assertNotSame($first->id, $second->id);
    }

    public function test_only_reserved_meetings_and_in_progress_recipients_are_notified(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-20 18:00:00'));
        $activeStudent = User::factory()->student()->create();
        $activeCoach = User::factory()->coach()->create();
        $inactiveStudent = User::factory()->student()->create(['status' => UserStatus::Graduated]);
        $inactiveCoach = User::factory()->coach()->create(['status' => UserStatus::Withdrawn]);

        $reserved = Meeting::factory()->forStudent($activeStudent)->forCoach($activeCoach)->create([
            'scheduled_at' => now()->addDay()->setTime(10, 0),
        ]);
        Meeting::factory()->forStudent($inactiveStudent)->forCoach($inactiveCoach)->create([
            'scheduled_at' => now()->addDay()->setTime(11, 0),
            'status' => MeetingStatus::Canceled,
        ]);
        Meeting::factory()->forStudent($inactiveStudent)->forCoach($inactiveCoach)->create([
            'scheduled_at' => now()->addDay()->setTime(12, 0),
            'status' => MeetingStatus::Completed,
        ]);
        $this->mock(MailChannel::class)->shouldReceive('send')->twice();

        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'eve'])
            ->assertExitCode(0);

        $this->assertSame(1, $activeStudent->notifications()->count());
        $this->assertSame(1, $activeCoach->notifications()->count());
        $this->assertSame(0, $inactiveStudent->notifications()->count());
        $this->assertSame(0, $inactiveCoach->notifications()->count());
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $activeStudent->id,
            'type' => MeetingReminderNotification::class,
        ]);
        $this->assertDatabaseCount('notifications', 2);
        $this->assertNotNull($reserved->fresh());
    }

    public function test_invalid_window_is_rejected(): void
    {
        $this->artisan('notifications:send-meeting-reminders', ['--window' => 'invalid'])
            ->assertExitCode(2);
    }
}
