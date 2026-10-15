<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Learning;

use App\Models\Chapter;
use App\Models\Enrollment;
use App\Models\Part;
use App\Models\Section;
use App\Models\SectionProgress;
use App\Services\Learning\LearningProgressService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LearningProgressServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_summarizes_only_published_content_and_completed_hierarchies(): void
    {
        $enrollment = Enrollment::factory()->create();
        $publishedPart = Part::factory()->for($enrollment->certification)->published()->create();
        $chapter = Chapter::factory()->forPart($publishedPart)->published()->create();
        $completedSection = Section::factory()->forChapter($chapter)->published()->create();
        $unreadSection = Section::factory()->forChapter($chapter)->published()->create();
        Section::factory()->forChapter($chapter)->draft()->create();
        Part::factory()->for($enrollment->certification)->draft()->create();

        SectionProgress::factory()->for($enrollment)->for($completedSection)->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(2, $summary->sectionsTotal);
        $this->assertSame(1, $summary->sectionsCompleted);
        $this->assertSame(0.5, $summary->sectionCompletionRatio);
        $this->assertSame(1, $summary->chaptersTotal);
        $this->assertSame(0, $summary->chaptersCompleted);
        $this->assertSame(1, $summary->partsTotal);
        $this->assertSame(0, $summary->partsCompleted);
        $this->assertSame(0.5, $summary->overallCompletionRatio);

        SectionProgress::factory()->for($enrollment)->for($unreadSection)->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(1, $summary->chaptersCompleted);
        $this->assertSame(1, $summary->partsCompleted);
        $this->assertSame(1.0, $summary->chapterCompletionRatio);
        $this->assertSame(1.0, $summary->partCompletionRatio);
    }

    public function test_returns_zero_summary_when_no_published_content_exists(): void
    {
        $enrollment = Enrollment::factory()->create();

        $summary = app(LearningProgressService::class)->summarize($enrollment);

        $this->assertSame(0, $summary->sectionsTotal);
        $this->assertSame(0, $summary->sectionsCompleted);
        $this->assertSame(0.0, $summary->overallCompletionRatio);
        $this->assertSame(0, $summary->chaptersTotal);
        $this->assertSame(0, $summary->partsTotal);
    }
}
