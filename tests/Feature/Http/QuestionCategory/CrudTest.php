<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QuestionCategory;

use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\QuestionCategory;
use App\Models\SectionQuestion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\ContentTestHelpers;
use Tests\TestCase;

class CrudTest extends TestCase
{
    use ContentTestHelpers, RefreshDatabase;

    public function test_admin_can_create_question_category(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $cert), [
                'name' => 'テクノロジー系',
                'slug' => 'technology',
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('question_categories', [
            'certification_id' => $cert->id,
            'slug' => 'technology',
        ]);
    }

    public function test_duplicate_slug_within_certification_rejected(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        QuestionCategory::factory()->forCertification($cert)->state(['slug' => 'tech'])->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $cert), [
                'name' => '別名',
                'slug' => 'tech',
            ])
            ->assertSessionHasErrors('slug');
    }

    public function test_same_slug_in_different_certification_allowed(): void
    {
        $admin = User::factory()->admin()->create();
        $certA = Certification::factory()->published()->create();
        $certB = Certification::factory()->published()->create();
        QuestionCategory::factory()->forCertification($certA)->state(['slug' => 'tech'])->create();

        $this->actingAs($admin)
            ->post(route('admin.certifications.question-categories.store', $certB), [
                'name' => 'Tech',
                'slug' => 'tech',
            ])
            ->assertRedirect();

        $this->assertSame(2, QuestionCategory::where('slug', 'tech')->count());
    }

    public function test_destroy_blocked_if_section_questions_exist(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        [, , $section] = $this->makePartChain($cert);
        $category = QuestionCategory::factory()->forCertification($cert)->create();
        SectionQuestion::factory()->forSection($section)->forCategory($category)->create();

        $this->actingAs($admin)
            ->deleteJson(route('admin.question-categories.destroy', $category))
            ->assertStatus(409);
    }

    public function test_destroy_allowed_when_no_questions(): void
    {
        $admin = User::factory()->admin()->create();
        $cert = Certification::factory()->published()->create();
        $category = QuestionCategory::factory()->forCertification($cert)->create();

        $this->actingAs($admin)
            ->delete(route('admin.question-categories.destroy', $category))
            ->assertRedirect();

        $this->assertDatabaseMissing('question_categories', ['id' => $category->id]);
    }

    public function test_non_assigned_coach_cannot_view(): void
    {
        $coach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.question-categories.index', $cert))
            ->assertForbidden();
    }

    public function test_assigned_coach_can_manage_categories_and_revoked_access_is_denied(): void
    {
        $coach = User::factory()->coach()->create();
        $secondCoach = User::factory()->coach()->create();
        $cert = Certification::factory()->published()->create();
        $otherCert = Certification::factory()->published()->create();
        $this->assignCoach($coach, $cert);
        $this->assignCoach($secondCoach, $cert);
        $category = QuestionCategory::factory()->forCertification($cert)->create();
        $categoryAfterRevocation = QuestionCategory::factory()->forCertification($cert)->create();
        $otherCategory = QuestionCategory::factory()->forCertification($otherCert)->create();

        $this->actingAs($coach)
            ->get(route('admin.certifications.question-categories.index', $cert))
            ->assertOk();

        $this->post(route('admin.certifications.question-categories.store', $cert), [
            'name' => '担当分野',
            'slug' => 'assigned-category',
        ])->assertRedirect();

        $this->patch(route('admin.question-categories.update', $category), [
            'name' => '更新分野',
            'slug' => $category->slug,
        ])->assertRedirect();
        $this->actingAs($secondCoach)
            ->patch(route('admin.question-categories.update', $categoryAfterRevocation), [
                'name' => '別コーチ更新',
                'slug' => $categoryAfterRevocation->slug,
            ])->assertRedirect();
        $this->actingAs($coach);

        $this->delete(route('admin.question-categories.destroy', $category))
            ->assertRedirect();

        $this->get(route('admin.certifications.question-categories.index', $otherCert))
            ->assertForbidden();
        $this->patch(route('admin.question-categories.update', $otherCategory), [
            'name' => '拒否',
            'slug' => $otherCategory->slug,
        ])->assertForbidden();

        CertificationCoachAssignment::query()
            ->where('certification_id', $cert->id)
            ->where('user_id', $coach->id)
            ->update(['unassigned_at' => now()]);

        $this->get(route('admin.certifications.question-categories.index', $cert))
            ->assertForbidden();
        $this->patch(route('admin.question-categories.update', $categoryAfterRevocation), [
            'name' => '解除後拒否',
            'slug' => $categoryAfterRevocation->slug,
        ])->assertForbidden();
        $this->patch(route('admin.question-categories.update', $otherCategory), [
            'name' => '拒否',
            'slug' => $otherCategory->slug,
        ])->assertForbidden();

        $this->actingAs($secondCoach)
            ->get(route('admin.certifications.question-categories.index', $cert))
            ->assertOk();
    }
}
