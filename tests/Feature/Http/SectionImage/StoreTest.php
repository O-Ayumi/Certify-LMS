<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SectionImage;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\SectionImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_upload_image(): void
    {
        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        [$part, $chapter, $section] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $file = UploadedFile::fake()->image('cover.png', 800, 600);

        $response = $this->actingAs($admin)
            ->postJson(route('admin.sections.images.store', $section), [
                'file' => $file,
            ])
            ->assertCreated()
            ->assertJsonStructure(['id', 'url', 'alt_placeholder']);

        $this->assertDatabaseHas('section_images', [
            'section_id' => $section->id,
            'mime_type' => 'image/png',
        ]);

        $payload = $response->json();
        $path = ltrim(str_replace('/storage/', '', $payload['url']), '/');
        Storage::disk('public')->assertExists($path);
    }

    public function test_rejects_oversized_file(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        [$part, $chapter, $section] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $file = UploadedFile::fake()->create('big.png', 3000, 'image/png');

        $this->actingAs($admin)
            ->postJson(route('admin.sections.images.store', $section), ['file' => $file])
            ->assertStatus(422);
    }

    public function test_rejects_invalid_mime(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();
        [$part, $chapter, $section] = $this->makePartChain(Certification::factory()->published()->create(), 'draft');

        $file = UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml');

        $this->actingAs($admin)
            ->postJson(route('admin.sections.images.store', $section), ['file' => $file])
            ->assertStatus(422);
    }

    public function test_assigned_coach_can_upload_and_delete_image_but_revoked_coach_cannot_delete(): void
    {
        Storage::fake('public');

        $coach = User::factory()->coach()->create();
        $secondCoach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $this->assignCoach($secondCoach, $cert);
        [, , $section] = $this->makePartChain($cert, 'draft');
        $otherCert = Certification::factory()->published()->create();
        [, , $otherSection] = $this->makePartChain($otherCert, 'draft');

        $this->actingAs($coach)
            ->postJson(route('admin.sections.images.store', $otherSection), [
                'file' => UploadedFile::fake()->image('foreign-image.png', 800, 600),
            ])
            ->assertForbidden();

        $response = $this->actingAs($coach)
            ->postJson(route('admin.sections.images.store', $section), [
                'file' => UploadedFile::fake()->image('coach-image.png', 800, 600),
            ])
            ->assertCreated();

        $image = SectionImage::findOrFail($response->json('id'));

        CertificationCoachAssignment::query()
            ->where('certification_id', $cert->id)
            ->where('user_id', $coach->id)
            ->update(['unassigned_at' => now()]);

        $this->deleteJson(route('admin.section-images.destroy', $image))
            ->assertForbidden();

        $this->actingAs($secondCoach)
            ->deleteJson(route('admin.section-images.destroy', $image))
            ->assertNoContent();
    }
}
