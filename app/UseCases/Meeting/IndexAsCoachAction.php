<?php

declare(strict_types=1);

namespace App\UseCases\Meeting;

use App\Models\Meeting;
use App\Models\User;

final class IndexAsCoachAction
{
    /** @return array{meetings: mixed, filter: string, studentFilter: ?string, enrollmentFilter: ?string} */
    public function __invoke(User $coach, array $filters): array
    {
        $filter = $filters['filter'] ?? 'upcoming';
        $studentId = $filters['student'] ?? null;
        $enrollmentId = $filters['enrollment'] ?? null;
        $query = Meeting::query()->with(['enrollment.certification', 'student'])->forCoach($coach)
            ->when($studentId, fn ($q, $id) => $q->where('student_id', $id))
            ->when($enrollmentId, fn ($q, $id) => $q->where('enrollment_id', $id));

        $meetings = match ($filter) {
            'past' => $query->past()->orderByDesc('scheduled_at')->paginate(20),
            'all' => $query->orderByDesc('scheduled_at')->paginate(20),
            default => $query->upcoming()->orderBy('scheduled_at')->paginate(20),
        };

        return ['meetings' => $meetings, 'filter' => $filter, 'studentFilter' => $studentId, 'enrollmentFilter' => $enrollmentId];
    }
}
