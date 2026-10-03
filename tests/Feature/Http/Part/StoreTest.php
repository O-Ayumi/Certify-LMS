<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Part;

use App\Models\Certification;
use App\Models\Part;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_create_part_as_draft_with_auto_order(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        Part::factory()->forCertification($cert)->state(['order' => 1])->create();

        $response = $this->actingAs($admin)
            ->post(route('admin.certifications.parts.store', $cert), [
                'title' => '第2部 ネットワーク',
                'description' => '通信プロトコルの基本',
            ]);

        $part = Part::where('title', '第2部 ネットワーク')->firstOrFail();
        $response->assertRedirect(route('admin.parts.show', $part));
        $this->assertSame('draft', $part->status->value);
        $this->assertSame(2, $part->order);
    }

    public function test_validation_title_required(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.parts.store', $cert), [
                'title' => '',
            ])
            ->assertSessionHasErrors('title');
    }

    public function test_non_assigned_coach_cannot_create(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.certifications.parts.store', $cert), ['title' => 'X'])
            ->assertForbidden();

        $this->patch(route('admin.parts.update', $part), ['title' => 'X'])
            ->assertForbidden();
    }

    public function test_assigned_coach_can_create_and_update_part(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $part = Part::factory()->forCertification($cert)->draft()->create();

        $this->actingAs($coach)
            ->post(route('admin.certifications.parts.store', $cert), ['title' => '追加 Part'])
            ->assertRedirect();

        $createdPart = Part::query()->where('title', '追加 Part')->firstOrFail();

        $this->post(route('admin.parts.publish', $createdPart))->assertRedirect();
        $this->post(route('admin.parts.unpublish', $createdPart))->assertRedirect();
        $this->patch(route('admin.certifications.parts.reorder', $cert), [
            'ids' => [$part->id, $createdPart->id],
        ])->assertRedirect();

        $this->patch(route('admin.parts.update', $part), [
            'title' => '更新 Part',
            'description' => '担当コーチによる更新',
        ])->assertRedirect(route('admin.parts.show', $part));

        $this->assertSame('更新 Part', $part->fresh()->title);
        $this->delete(route('admin.parts.destroy', $part))
            ->assertRedirect(route('admin.certifications.parts.index', $cert));
    }
}
