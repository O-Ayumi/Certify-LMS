<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaReply\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_body_only(): void
    {
        $user = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($user)->create();

        $result = (new UpdateAction)($reply, '更新本文');

        $this->assertSame('更新本文', $result->body);
        $this->assertSame($thread->id, $result->qa_thread_id);
        $this->assertSame($user->id, $result->user_id);
    }
}
