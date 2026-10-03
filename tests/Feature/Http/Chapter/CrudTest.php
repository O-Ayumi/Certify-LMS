<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Chapter;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Chapter;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_store_creates_draft_chapter(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.parts.chapters.store', $part), ['title' => '第1章'])
            ->assertRedirect();

        $chapter = Chapter::where('title', '第1章')->firstOrFail();
        $this->assertSame('draft', $chapter->status->value);
        $this->assertSame(1, $chapter->order);
    }

    public function test_publish_then_unpublish(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($admin)
            ->post(route('admin.chapters.publish', $chapter))
            ->assertRedirect();
        $this->assertSame('published', $chapter->fresh()->status->value);

        $this->actingAs($admin)
            ->post(route('admin.chapters.unpublish', $chapter))
            ->assertRedirect();
        $this->assertSame('draft', $chapter->fresh()->status->value);
    }

    public function test_destroy_draft_only(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->published()->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.chapters.destroy', $chapter))
            ->assertStatus(409);
    }

    public function test_assigned_coach_can_view_create_and_update_chapter(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();
        $chapter = Chapter::factory()->forPart($part)->draft()->create();

        $this->actingAs($coach)
            ->get(route('admin.chapters.show', $chapter))
            ->assertOk();

        $this->post(route('admin.parts.chapters.store', $part), ['title' => '追加 Chapter'])
            ->assertRedirect();

        $createdChapter = Chapter::query()->where('title', '追加 Chapter')->firstOrFail();

        $this->post(route('admin.chapters.publish', $createdChapter))->assertRedirect();
        $this->post(route('admin.chapters.unpublish', $createdChapter))->assertRedirect();
        $this->patch(route('admin.parts.chapters.reorder', $part), [
            'ids' => [$chapter->id, $createdChapter->id],
        ])->assertRedirect();

        $this->patch(route('admin.chapters.update', $chapter), [
            'title' => '更新 Chapter',
            'description' => '担当コーチによる更新',
        ])->assertRedirect(route('admin.chapters.show', $chapter));

        $this->assertSame('更新 Chapter', $chapter->fresh()->title);
        $this->delete(route('admin.chapters.destroy', $createdChapter))->assertRedirect();

        $otherCert = Certification::factory()->published()->create();
        $otherPart = Part::factory()->forCertification($otherCert)->draft()->create();
        $otherChapter = Chapter::factory()->forPart($otherPart)->draft()->create();

        $this->get(route('admin.chapters.show', $otherChapter))->assertForbidden();
        $this->patch(route('admin.chapters.update', $otherChapter), ['title' => '担当外'])
            ->assertForbidden();

        CertificationCoachAssignment::query()
            ->where('certification_id', $cert->id)
            ->where('user_id', $coach->id)
            ->update(['unassigned_at' => now()]);

        $this->get(route('admin.chapters.show', $chapter))->assertForbidden();
        $this->patch(route('admin.chapters.update', $chapter), ['title' => '拒否'])
            ->assertForbidden();
    }
}
