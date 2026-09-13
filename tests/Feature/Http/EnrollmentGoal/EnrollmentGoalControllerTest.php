<?php

declare(strict_types=1);

namespace Tests\Feature\Http\EnrollmentGoal;

use App\Enums\UserStatus;
use App\Models\Certification;
use App\Models\CertificationCoachAssignment;
use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use App\Models\User;
use Database\Seeders\EnrollmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EnrollmentGoalControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_manage_goal_without_changing_parent_or_injecting_achievement(): void
    {
        $enrollment = Enrollment::factory()->create();
        $other = Enrollment::factory()->create();
        $this->actingAs($enrollment->user);
        $payload = ['title' => str_repeat('あ', 100), 'description' => str_repeat('詳', 1000), 'target_date' => '2026-01-01', 'enrollment_id' => $other->id, 'achieved_at' => now()];
        $this->post(route('enrollments.goals.store', $enrollment), $payload)->assertRedirect(route('enrollments.show', $enrollment));
        $goal = $enrollment->goals()->sole();
        $this->assertNull($goal->achieved_at);
        $this->get(route('enrollment-goals.edit', $goal))->assertOk()->assertSee($goal->title);
        $this->patch(route('enrollment-goals.update', $goal), array_merge($payload, ['title' => '更新した目標', 'description' => '', 'target_date' => '']))->assertRedirect();
        $goal->refresh();
        $this->assertSame($enrollment->id, $goal->enrollment_id);
        $this->assertNull($goal->description);
        $this->assertNull($goal->target_date);
        $this->assertNull($goal->achieved_at);
        $this->post(route('enrollment-goals.markAchieved', $goal))->assertRedirect();
        $achievedAt = $goal->refresh()->achieved_at;
        $this->assertNotNull($achievedAt);
        $this->travel(1)->hours();
        $this->post(route('enrollment-goals.markAchieved', $goal))->assertRedirect();
        $this->assertFalse($achievedAt->equalTo($goal->refresh()->achieved_at));
        $this->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertRedirect();
        $this->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertRedirect();
        $this->assertNull($goal->refresh()->achieved_at);
        $this->delete(route('enrollment-goals.destroy', $goal))->assertRedirect();
        $this->assertModelMissing($goal);
        $this->get(route('enrollment-goals.edit', $goal))->assertNotFound();
    }

    public function test_every_endpoint_rejects_non_owner_and_inactive_owner_without_mutation(): void
    {
        $goal = EnrollmentGoal::factory()->create();
        $owner = $goal->enrollment->user;
        $actors = [User::factory()->create(), User::factory()->coach()->create(), User::factory()->admin()->create()];
        CertificationCoachAssignment::create(['certification_id' => $goal->enrollment->certification_id, 'user_id' => $actors[1]->id, 'assigned_by_user_id' => $actors[2]->id, 'assigned_at' => now()]);
        foreach ($actors as $actor) {
            $this->assertOperationsForbidden($actor, $goal);
        }
        foreach ([UserStatus::Graduated, UserStatus::Withdrawn, UserStatus::Invited] as $status) {
            $owner->update(['status' => $status]);
            $this->assertOperationsForbidden($owner, $goal);
            $this->get(route('enrollments.show', $goal->enrollment))->assertForbidden();
        }
        $this->assertDatabaseCount('enrollment_goals', 1);
        $this->assertSame('過去問を解き終える', $goal->refresh()->title);
        $this->assertNull($goal->achieved_at);
    }

    private function assertOperationsForbidden(User $user, EnrollmentGoal $goal): void
    {
        $this->actingAs($user);
        $this->post(route('enrollments.goals.store', $goal->enrollment), ['title' => '不正'])->assertForbidden();
        $this->get(route('enrollment-goals.edit', $goal))->assertForbidden();
        $this->patch(route('enrollment-goals.update', $goal), ['title' => '不正'])->assertForbidden();
        $this->delete(route('enrollment-goals.destroy', $goal))->assertForbidden();
        $this->post(route('enrollment-goals.markAchieved', $goal))->assertForbidden();
        $this->delete(route('enrollment-goals.unmarkAchieved', $goal))->assertForbidden();
    }

    public function test_validation_for_both_create_and_update(): void
    {
        $goal = EnrollmentGoal::factory()->create();
        $this->actingAs($goal->enrollment->user);
        foreach ([['title' => ' '], ['title' => str_repeat('あ', 101)], ['title' => []], ['description' => str_repeat('あ', 1001)], ['description' => []], ['target_date' => '2026-02-30'], ['target_date' => 'invalid']] as $invalid) {
            $payload = array_merge(['title' => '目標'], $invalid);
            $this->postJson(route('enrollments.goals.store', $goal->enrollment), $payload)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
            $this->patchJson(route('enrollment-goals.update', $goal), $payload)->assertUnprocessable()->assertJsonValidationErrors(array_keys($invalid));
        }
        $this->post(route('enrollments.goals.store', $goal->enrollment), ['title' => '期日なし'])->assertRedirect();
        $this->assertDatabaseCount('enrollment_goals', 2);
    }

    public function test_view_is_ordered_and_staff_can_only_read(): void
    {
        $enrollment = Enrollment::factory()->create();
        foreach ([['達成済み', '2026-01-01', now()], ['期日なし', null, null], ['期日が後', '2026-10-10', null], ['期日が先', '2026-09-01', null]] as [$title, $date, $achieved]) {
            EnrollmentGoal::factory()->for($enrollment)->create(['title' => $title, 'target_date' => $date, 'achieved_at' => $achieved]);
        }
        $this->actingAs($enrollment->user)->get(route('enrollments.show', $enrollment))->assertOk()->assertSeeInOrder(['期日が先', '期日が後', '期日なし', '達成済み'])->assertSee('目標を追加')->assertSee('line-through', false);
        $admin = User::factory()->admin()->create();
        $coach = User::factory()->coach()->create();
        CertificationCoachAssignment::create(['certification_id' => $enrollment->certification_id, 'user_id' => $coach->id, 'assigned_by_user_id' => $admin->id, 'assigned_at' => now()]);
        foreach ([$admin, $coach] as $staff) {
            $response = $this->actingAs($staff)->get(route('enrollments.show', $enrollment))->assertOk()->assertSee('達成済み')->assertDontSee('目標を追加');
            foreach ($enrollment->goals as $goal) {
                $response->assertDontSee(route('enrollment-goals.edit', $goal))->assertDontSee(route('enrollment-goals.markAchieved', $goal));
            }
        }
        foreach ([User::factory()->create(), User::factory()->coach()->create()] as $outsider) {
            $this->actingAs($outsider)->get(route('enrollments.show', $enrollment))->assertForbidden();
        }
    }

    public function test_parent_soft_deletion_and_database_cascade_physically_delete_goals(): void
    {
        $goal = EnrollmentGoal::factory()->create();
        $this->actingAs($goal->enrollment->user)->delete(route('enrollments.destroy', $goal->enrollment))->assertRedirect();
        $this->assertDatabaseHas('enrollment_goals', ['id' => $goal->id]);
        $this->assertSoftDeleted($goal->enrollment);
        $goal = EnrollmentGoal::factory()->create();
        DB::table('enrollments')->where('id', $goal->enrollment_id)->delete();
        $this->assertModelMissing($goal);
    }

    public function test_guest_and_missing_resources_are_rejected(): void
    {
        $goal = EnrollmentGoal::factory()->create();
        foreach ([['POST', route('enrollments.goals.store', $goal->enrollment)], ['GET', route('enrollment-goals.edit', $goal)], ['PATCH', route('enrollment-goals.update', $goal)], ['DELETE', route('enrollment-goals.destroy', $goal)], ['POST', route('enrollment-goals.markAchieved', $goal)], ['DELETE', route('enrollment-goals.unmarkAchieved', $goal)]] as [$method, $url]) {
            $this->json($method, $url)->assertUnauthorized();
        }
        $this->actingAs($goal->enrollment->user)->post('/enrollments/missing/goals', ['title' => '目標'])->assertNotFound();
        $this->get('/enrollment-goals/missing/edit')->assertNotFound();
    }

    public function test_seeder_supplies_fixed_and_demo_students_with_both_goal_states(): void
    {
        $fixed = User::factory()->create(['email' => 'student@certify-lms.test']);
        $demo = User::factory()->create();
        Certification::factory()->published()->create();
        $this->seed(EnrollmentSeeder::class);
        foreach ([$fixed, $demo] as $student) {
            $enrollment = Enrollment::where('user_id', $student->id)->sole();
            $this->assertSame(1, $enrollment->goals()->whereNull('achieved_at')->count());
            $this->assertSame(1, $enrollment->goals()->whereNotNull('achieved_at')->count());
        }
    }
}
