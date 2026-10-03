<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Section;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\Section;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_store_section_includes_body(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.chapters.sections.store', $chapter), [
                'title' => 'はじめに',
                'body' => '## 概要

本セクションでは...',
            ])
            ->assertRedirect();

        $section = Section::where('title', 'はじめに')->firstOrFail();
        $this->assertStringContainsString('概要', $section->body);
        $this->assertSame('draft', $section->status->value);
    }

    public function test_update_updates_body(): void
    {
        $admin = User::factory()->admin()->create();
        [$part, $chapter, $section] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $this->actingAs($admin)
            ->patch(route('admin.sections.update', $section), [
                'title' => '新タイトル',
                'body' => 'updated body',
            ])
            ->assertRedirect();

        $section->refresh();
        $this->assertSame('新タイトル', $section->title);
        $this->assertSame('updated body', $section->body);
    }

    public function test_preview_returns_html(): void
    {
        $admin = User::factory()->admin()->create();
        [$part, $chapter, $section] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $this->actingAs($admin)
            ->postJson(route('admin.sections.preview', $section), [
                'body' => "# タイトル\n\n本文",
            ])
            ->assertOk()
            ->assertJsonStructure(['html']);
    }

    public function test_assigned_coach_can_view_create_and_update_section(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        [$part, $chapter, $section] = $this->makePartChain($cert, 'draft');

        $this->actingAs($coach)
            ->get(route('admin.sections.show', $section))
            ->assertOk();

        $this->post(route('admin.chapters.sections.store', $chapter), [
            'title' => '追加 Section',
            'body' => '本文',
        ])->assertRedirect();

        $createdSection = Section::query()->where('title', '追加 Section')->firstOrFail();

        $this->post(route('admin.sections.publish', $createdSection))->assertRedirect();
        $this->post(route('admin.sections.unpublish', $createdSection))->assertRedirect();
        $this->patch(route('admin.chapters.sections.reorder', $chapter), [
            'ids' => [$section->id, $createdSection->id],
        ])->assertRedirect();

        $this->patch(route('admin.sections.update', $section), [
            'title' => '更新 Section',
            'body' => '更新本文',
        ])->assertRedirect(route('admin.sections.show', $section));

        $this->assertSame('更新 Section', $section->fresh()->title);
        $this->delete(route('admin.sections.destroy', $createdSection))->assertRedirect();

        $otherCert = Certification::factory()->published()->create();
        [, $otherChapter, $otherSection] = $this->makePartChain($otherCert, 'draft');

        $this->get(route('admin.sections.show', $otherSection))->assertForbidden();
        $this->patch(route('admin.sections.update', $otherSection), [
            'title' => '担当外',
            'body' => '担当外',
        ])->assertForbidden();

        CertificationCoachAssignment::query()
            ->where('certification_id', $cert->id)
            ->where('user_id', $coach->id)
            ->update(['unassigned_at' => now()]);

        $this->get(route('admin.sections.show', $section))->assertForbidden();
        $this->patch(route('admin.sections.update', $section), [
            'title' => '拒否',
            'body' => '拒否',
        ])->assertForbidden();
    }
}
