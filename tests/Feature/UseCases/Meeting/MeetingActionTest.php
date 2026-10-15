<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\MeetingMemo;
use App\Models\User;
use App\Services\MeetingAvailabilityService;
use App\UseCases\Meeting\CancelAction;
use App\UseCases\Meeting\FetchAvailabilityAction;
use App\UseCases\Meeting\IndexAction;
use App\UseCases\Meeting\IndexAsCoachAction;
use App\UseCases\Meeting\ShowAction;
use App\UseCases\Meeting\UpsertMemoAction;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeetingActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_show_action_loads_meeting_relations(): void
    {
        $coach = User::factory()->coach()->create();
        $student = User::factory()->student()->create();
        $meeting = Meeting::factory()->reserved()->forCoach($coach)->forStudent($student)->create();
        $result = app(ShowAction::class)($meeting);

        $this->assertTrue($result->relationLoaded('enrollment'));
        $this->assertTrue($result->relationLoaded('coach'));
        $this->assertTrue($result->relationLoaded('student'));
    }

    public function test_index_actions_scope_meetings_to_the_actor(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 3]);
        $coach = User::factory()->coach()->create();
        $otherStudent = User::factory()->student()->create();
        $own = Meeting::factory()->reserved()->forStudent($student)->forCoach($coach)->create();
        Meeting::factory()->reserved()->forStudent($otherStudent)->forCoach($coach)->create();

        $studentResult = app(IndexAction::class)($student, 'all');
        $coachResult = app(IndexAsCoachAction::class)($coach, []);

        $this->assertTrue($studentResult['meetings']->getCollection()->contains('id', $own->id));
        $this->assertTrue($coachResult['meetings']->getCollection()->contains('id', $own->id));
    }

    public function test_fetch_availability_action_delegates_to_availability_service(): void
    {
        $enrollment = Enrollment::factory()->create();
        $service = app(MeetingAvailabilityService::class);

        $result = app(FetchAvailabilityAction::class)($enrollment, Carbon::parse('2026-06-01'), $service);

        $this->assertCount(0, $result);
    }

    public function test_upsert_memo_action_creates_and_updates_memo(): void
    {
        $meeting = Meeting::factory()->completed()->create();
        $action = app(UpsertMemoAction::class);
        $action($meeting, '初回メモ');
        $action($meeting, '更新メモ');

        $this->assertDatabaseHas('meeting_memos', ['meeting_id' => $meeting->id, 'body' => '更新メモ']);
        $this->assertSame(1, MeetingMemo::query()->where('meeting_id', $meeting->id)->count());
    }

    public function test_cancel_action_cancels_reserved_meeting(): void
    {
        $student = User::factory()->student()->create(['max_meetings' => 5]);
        $meeting = Meeting::factory()->reserved()->forStudent($student)->create(['scheduled_at' => now()->addDay()]);
        $action = app(CancelAction::class);
        $action($meeting, $student);

        $this->assertSame(MeetingStatus::Canceled, $meeting->fresh()->status);
        $this->assertDatabaseHas('meeting_quota_transactions', ['related_meeting_id' => $meeting->id, 'amount' => 1]);
    }
}
