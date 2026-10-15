<?php

declare(strict_types=1);

namespace App\UseCases\Learning;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use App\Services\Learning\LearningProgressService;
use App\Services\LearningHourTargetService;
use App\Services\SectionQuestionScoreService;
use App\Services\StreakService;

/**
 * /learning/enrollments/{enrollment} (2 階層目、教材 Part 一覧) のデータを準備する Action。
 */
final class ShowEnrollmentAction
{
    public function __construct(
        private readonly StreakService $streakService,
        private readonly LearningHourTargetService $hourTargetService,
        private readonly SectionQuestionScoreService $scoreService,
        private readonly LearningProgressService $progressService,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function __invoke(Enrollment $enrollment, string $tab = 'contents'): array
    {
        $enrollment->loadMissing(['certification', 'user', 'learningHourTarget']);
        $parts = $enrollment->certification
            ?->parts()
            ->where('status', ContentStatus::Published->value)
            ->ordered()
            ->with([
                'chapters' => fn ($query) => $query
                    ->where('status', ContentStatus::Published->value)
                    ->ordered()
                    ->with([
                        'sections' => fn ($q) => $q
                            ->where('status', ContentStatus::Published->value)
                            ->ordered(),
                    ]),
            ])->get() ?? collect();

        $sectionProgresses = $enrollment->sectionProgresses()
            ->pluck('section_id')
            ->all();

        $quizScoreSummaries = $tab === 'quizzes'
            ? $this->scoreService->batchSummarize($enrollment->user, $enrollment)
            : collect();

        return [
            'enrollment' => $enrollment,
            'parts' => $parts,
            'tab' => $tab,
            'completedSectionIds' => $sectionProgresses,
            'progress' => $this->progressService->summarize($enrollment),
            'streak' => $this->streakService->calculate($enrollment->user),
            'hourTargetSummary' => $this->hourTargetService->compute($enrollment),
            'quizScoreSummaries' => $quizScoreSummaries,
        ];
    }
}
