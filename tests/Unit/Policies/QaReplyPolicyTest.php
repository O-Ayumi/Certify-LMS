<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\Policies\QaReplyPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaReplyPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_allows_students_and_assigned_coaches_only(): void
    {
        $policy = new QaReplyPolicy;
        $student = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => $admin->id,
            'assigned_at' => now(),
        ]);
        $thread = QaThread::factory()->for($certification)->create();

        $this->assertTrue($policy->create($student, $thread));
        $this->assertTrue($policy->create($coach, $thread));
        $this->assertFalse($policy->create($admin, $thread));
        $this->assertFalse($policy->create(User::factory()->coach()->create(), QaThread::factory()->create()));
    }

    public function test_update_and_delete_require_reply_owner_and_visible_assignment(): void
    {
        $policy = new QaReplyPolicy;
        $student = User::factory()->student()->create();
        $other = User::factory()->student()->create();
        $reply = QaReply::factory()->for(QaThread::factory(), 'thread')->for($student)->create();

        $this->assertTrue($policy->update($student, $reply));
        $this->assertTrue($policy->delete($student, $reply));
        $this->assertFalse($policy->update($other, $reply));
        $this->assertFalse($policy->update(User::factory()->admin()->create(), $reply));
    }
}
