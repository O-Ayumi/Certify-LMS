<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentNote;

use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentNoteControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_and_assigned_coach_can_manage_notes_with_author_bound_server_side(): void
    {
        [$enrollment, $admin, $coach, $otherCoach] = $this->enrollmentWithStaff();
        $this->actingAs($coach)->post(route('enrollments.notes.store', $enrollment), [
            'body' => '担当コーチの観察メモ',
            'enrollment_id' => Enrollment::factory()->create()->id,
            'author_user_id' => $admin->id,
        ])->assertRedirect();
        $note = $enrollment->notes()->sole();
        $this->assertSame($coach->id, $note->author_user_id);

        $this->actingAs($otherCoach)->get(route('enrollments.show', $enrollment))->assertForbidden();
        $this->actingAs($admin)->patch(route('enrollment-notes.update', $note), ['body' => '管理者が更新'])->assertRedirect();
        $this->assertSame('管理者が更新', $note->refresh()->body);
        $this->actingAs($admin)->delete(route('enrollment-notes.destroy', $note))->assertRedirect();
        $this->assertModelMissing($note);
    }

    public function test_coach_can_only_edit_or_delete_own_note_but_can_read_other_coach_note(): void
    {
        [$enrollment, , $coach, $otherCoach] = $this->enrollmentWithStaff();
        $own = EnrollmentNote::factory()->for($enrollment)->create(['author_user_id' => $coach->id]);
        $other = EnrollmentNote::factory()->for($enrollment)->create(['author_user_id' => $otherCoach->id]);

        $response = $this->actingAs($coach)->get(route('enrollments.show', $enrollment));
        $response->assertOk()->assertSee($own->body)->assertSee($other->body);
        $this->patch(route('enrollment-notes.update', $other), ['body' => '不正更新'])->assertForbidden();
        $this->delete(route('enrollment-notes.destroy', $other))->assertForbidden();
    }

    public function test_coach_loses_note_access_after_being_unassigned_from_certification(): void
    {
        [$enrollment, $admin, $coach] = $this->enrollmentWithStaff();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_user_id' => $coach->id]);
        DB::table('certification_coach_assignments')
            ->where('certification_id', $enrollment->certification_id)
            ->where('user_id', $coach->id)
            ->update(['unassigned_at' => now()]);

        $this->actingAs($coach)->get(route('enrollments.show', $enrollment))->assertForbidden();
        $this->patch(route('enrollment-notes.update', $note), ['body' => '担当解除後の更新'])->assertForbidden();
        $this->delete(route('enrollment-notes.destroy', $note))->assertForbidden();
        $this->actingAs($admin)->get(route('enrollments.show', $enrollment))->assertOk()->assertSee($note->body);
    }

    public function test_soft_deleted_author_is_retained_and_displayed_as_author(): void
    {
        [$enrollment, , $coach] = $this->enrollmentWithStaff();
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_user_id' => $coach->id]);
        $coach->delete();

        $this->actingAs(User::factory()->admin()->create())
            ->get(route('enrollments.show', $enrollment))
            ->assertOk()
            ->assertSee($coach->name);
        $this->assertDatabaseHas('enrollment_notes', ['id' => $note->id, 'author_user_id' => $coach->id]);
    }

    public function test_students_and_unassigned_coaches_are_denied_and_students_do_not_see_note_section(): void
    {
        [$enrollment, , , $otherCoach] = $this->enrollmentWithStaff();
        EnrollmentNote::factory()->for($enrollment)->create();
        $this->actingAs($enrollment->user)->get(route('enrollments.show', $enrollment))
            ->assertOk()->assertDontSee('コーチメモ');
        $this->post(route('enrollments.notes.store', $enrollment), ['body' => '不正'])->assertForbidden();
        $this->actingAs($otherCoach)->post(route('enrollments.notes.store', $enrollment), ['body' => '不正'])->assertForbidden();
    }

    public function test_body_validation_and_parent_soft_delete_boundary_are_enforced(): void
    {
        [$enrollment, , $coach] = $this->enrollmentWithStaff();
        $this->actingAs($coach);
        foreach ([['body' => ''], ['body' => str_repeat('あ', 2001)], ['body' => []]] as $invalid) {
            $this->postJson(route('enrollments.notes.store', $enrollment), $invalid)
                ->assertUnprocessable()->assertJsonValidationErrors('body');
        }
        $note = EnrollmentNote::factory()->for($enrollment)->create(['author_user_id' => $coach->id]);
        $enrollment->delete();
        $this->get(route('enrollment-notes.edit', $note))->assertForbidden();
        $this->post(route('enrollments.notes.store', $enrollment), ['body' => '不正'])->assertForbidden();

        DB::table('enrollments')->where('id', $enrollment->id)->delete();
        $this->assertModelMissing($note);
    }

    public function test_guest_and_missing_resources_are_rejected(): void
    {
        $enrollment = Enrollment::factory()->create();
        $note = EnrollmentNote::factory()->for($enrollment)->create();
        $this->json('POST', route('enrollments.notes.store', $enrollment), ['body' => 'x'])->assertUnauthorized();
        $this->json('GET', route('enrollment-notes.edit', $note))->assertUnauthorized();
        $this->json('PATCH', route('enrollment-notes.update', $note), ['body' => 'x'])->assertUnauthorized();
        $this->json('DELETE', route('enrollment-notes.destroy', $note))->assertUnauthorized();
        $this->actingAs(User::factory()->admin()->create())->get('/enrollment-notes/missing/edit')->assertNotFound();
    }

    /** @return array{0: Enrollment, 1: User, 2: User, 3: User} */
    private function enrollmentWithStaff(): array
    {
        $enrollment = Enrollment::factory()->create();
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        $otherCoach = User::factory()->coach()->create();
        CertificationCoachAssignment::create([
            'certification_id' => $enrollment->certification_id,
            'user_id' => $coach->id,
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        return [$enrollment, $admin, $coach, $otherCoach];
    }
}
