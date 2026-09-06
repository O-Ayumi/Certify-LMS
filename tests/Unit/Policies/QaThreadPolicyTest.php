<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaThreadPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_view_matrix_for_student_coach_and_admin(): void
    {
        $policy = new QaThreadPolicy;
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $assigned = Certification::factory()->published()->create();
        $unpublished = Certification::factory()->archived()->create();
        $assigned->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);

        $this->assertTrue($policy->view($student, QaThread::factory()->for($assigned)->create()));
        $this->assertTrue($policy->view($coach, QaThread::factory()->for($assigned)->create()));
        $this->assertFalse($policy->view($coach, QaThread::factory()->for(Certification::factory()->published())->create()));
        $this->assertFalse($policy->view($student, QaThread::factory()->for($unpublished)->create()));
        $this->assertTrue($policy->view($admin, QaThread::factory()->for($unpublished)->create()));
        $this->assertTrue($policy->viewAny($student));
    }

    public function test_update_resolve_and_delete_are_owner_or_admin_rules(): void
    {
        $policy = new QaThreadPolicy;
        $owner = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->for($owner)->create();
        $withReply = QaThread::factory()->for($owner)->create();
        QaReply::factory()->for($withReply, 'thread')->create();

        $this->assertTrue($policy->update($owner, $thread));
        $this->assertFalse($policy->update($other, $thread));
        $this->assertTrue($policy->resolve($owner, $thread));
        $this->assertFalse($policy->resolve($admin, $thread));
        $this->assertTrue($policy->delete($owner, $thread));
        $this->assertFalse($policy->delete($owner, $withReply));
        $this->assertTrue($policy->delete($admin, $withReply));
    }
}
