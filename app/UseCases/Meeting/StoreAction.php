<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Enums\MeetingStatus;
use App\Events\MeetingReserved;
use App\Exceptions\MeetingQuota\InsufficientMeetingQuotaException;
use App\Exceptions\Mentoring\MeetingNoAvailableCoachException;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\Meeting;
use App\Models\User;
use App\Services\CoachMeetingLoadService;
use App\Services\GoogleCalendarService;
use App\Services\MeetingAvailabilityService;
use App\Services\MeetingQuotaService;
use App\UseCases\MeetingQuota\ConsumeQuotaAction;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly MeetingAvailabilityService $availability,
        private readonly CoachMeetingLoadService $coachLoad,
        private readonly MeetingQuotaService $quota,
        private readonly ConsumeQuotaAction $consume,
        private readonly GoogleCalendarService $calendar,
    ) {}

    public function __invoke(Enrollment $enrollment, Carbon $scheduledAt, ?string $topic): Meeting
    {
        $student = $enrollment->user;

        return DB::transaction(function () use ($enrollment, $student, $scheduledAt, $topic) {
            if ($this->quota->remaining($student) < 1) {
                throw new InsufficientMeetingQuotaException;
            }
            $this->availability->validateSlot($enrollment->certification, $scheduledAt);
            $candidates = $this->findAvailableCoaches($enrollment->certification, $scheduledAt);
            if ($candidates->isEmpty()) {
                throw new MeetingNoAvailableCoachException;
            }
            $coach = $this->coachLoad->leastLoadedCoach($candidates);
            try {
                $meeting = Meeting::create([
                    'enrollment_id' => $enrollment->id, 'coach_id' => $coach->id, 'student_id' => $student->id,
                    'scheduled_at' => $scheduledAt, 'status' => MeetingStatus::Reserved->value, 'topic' => $topic,
                    'meeting_url_snapshot' => $coach->meeting_url,
                ]);
            } catch (UniqueConstraintViolationException $e) {
                throw new MeetingNoAvailableCoachException($e);
            }
            $transaction = ($this->consume)($student, $meeting->id);
            $meeting->update(['meeting_quota_transaction_id' => $transaction->id]);
            DB::afterCommit(fn () => MeetingReserved::dispatch($meeting));

            return $meeting->fresh();
        });
    }

    /** @return Collection<int, User> */
    private function findAvailableCoaches(Certification $certification, Carbon $scheduledAt): Collection
    {
        $time = $scheduledAt->format('H:i:s');
        $endTime = $scheduledAt->copy()->addHour()->format('H:i:s');

        return $certification->coaches()->with('googleCredential')
            ->whereHas('coachAvailabilities', fn ($q) => $q->where('day_of_week', $scheduledAt->dayOfWeek)->where('is_active', true)->where('start_time', '<=', $time)->where('end_time', '>=', $endTime))
            ->whereDoesntHave('meetingsAsCoach', fn ($q) => $q->where('scheduled_at', $scheduledAt)->whereIn('status', [MeetingStatus::Reserved->value, MeetingStatus::Completed->value]))
            ->get()->filter(fn (User $coach) => ! $this->calendar->isBusy($coach, $scheduledAt))->values();
    }
}
