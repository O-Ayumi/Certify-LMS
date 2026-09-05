<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\DestroyAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_thread_and_replies_and_allows_admin_with_replies(): void
    {
        $admin = User::factory()->admin()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        (new DestroyAction)($thread, $admin);

        $this->assertDatabaseMissing('qa_threads', ['id' => $thread->id]);
        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }

    public function test_student_with_replies_is_rejected(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->for($student)->create();
        QaReply::factory()->for($thread, 'thread')->create();

        $this->expectException(AuthorizationException::class);
        (new DestroyAction)($thread, $student);
    }
}
