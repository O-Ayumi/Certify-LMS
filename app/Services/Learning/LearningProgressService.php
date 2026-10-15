<?php

declare(strict_types=1);

namespace App\Services\Learning;

use App\Enums\ContentStatus;
use App\Models\Enrollment;
use Illuminate\Support\Facades\DB;

/** 教材の Section / Chapter / Part / 資格単位の学習進捗を集計する。 */
final class LearningProgressService
{
    public function summarize(Enrollment $enrollment): ProgressSummary
    {
        $totals = DB::table('sections')
            ->join('chapters', 'chapters.id', '=', 'sections.chapter_id')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->leftJoin('section_progresses', function ($join) use ($enrollment) {
                $join->on('section_progresses.section_id', '=', 'sections.id')
                    ->where('section_progresses.enrollment_id', '=', $enrollment->id);
            })
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->where('sections.status', ContentStatus::Published->value)
            ->selectRaw('COUNT(sections.id) AS sections_total, COUNT(section_progresses.id) AS sections_completed')
            ->first() ?? (object) ['sections_total' => 0, 'sections_completed' => 0];

        $partsTotal = DB::table('parts')
            ->where('certification_id', $enrollment->certification_id)
            ->where('status', ContentStatus::Published->value)
            ->count();

        $chaptersTotal = DB::table('chapters')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->count();

        $sectionsTotal = (int) $totals->sections_total;
        $sectionsCompleted = (int) $totals->sections_completed;
        $sectionRatio = $this->ratio($sectionsCompleted, $sectionsTotal);
        $chaptersCompleted = $this->countCompletedChapters($enrollment);
        $partsCompleted = $this->countCompletedParts($enrollment);

        return new ProgressSummary(
            sectionsTotal: $sectionsTotal,
            sectionsCompleted: $sectionsCompleted,
            sectionCompletionRatio: $sectionRatio,
            chaptersTotal: $chaptersTotal,
            chaptersCompleted: $chaptersCompleted,
            chapterCompletionRatio: $this->ratio($chaptersCompleted, (int) $chaptersTotal),
            partsTotal: (int) $partsTotal,
            partsCompleted: $partsCompleted,
            partCompletionRatio: $this->ratio($partsCompleted, (int) $partsTotal),
            overallCompletionRatio: $sectionRatio,
        );
    }

    private function ratio(int $completed, int $total): float
    {
        return $total === 0 ? 0.0 : round($completed / $total, 4);
    }

    private function countCompletedChapters(Enrollment $enrollment): int
    {
        $query = DB::table('chapters')
            ->join('parts', 'parts.id', '=', 'chapters.part_id')
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->where('chapters.status', ContentStatus::Published->value)
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->groupBy('chapters.id');

        return $this->countFullyCompletedRows($query, $enrollment);
    }

    private function countCompletedParts(Enrollment $enrollment): int
    {
        $query = DB::table('parts')
            ->where('parts.certification_id', $enrollment->certification_id)
            ->where('parts.status', ContentStatus::Published->value)
            ->leftJoin('chapters', function ($join) {
                $join->on('chapters.part_id', '=', 'parts.id')
                    ->where('chapters.status', ContentStatus::Published->value);
            })
            ->leftJoin('sections', function ($join) {
                $join->on('sections.chapter_id', '=', 'chapters.id')
                    ->where('sections.status', ContentStatus::Published->value);
            })
            ->groupBy('parts.id');

        return $this->countFullyCompletedRows($query, $enrollment);
    }

    private function countFullyCompletedRows($query, Enrollment $enrollment): int
    {
        $rows = $query->leftJoin('section_progresses', function ($join) use ($enrollment) {
            $join->on('section_progresses.section_id', '=', 'sections.id')
                ->where('section_progresses.enrollment_id', '=', $enrollment->id);
        })->selectRaw('COUNT(sections.id) AS total, COUNT(section_progresses.id) AS done')->get();

        return $rows->filter(fn ($row) => (int) $row->total > 0 && (int) $row->total === (int) $row->done)->count();
    }
}
