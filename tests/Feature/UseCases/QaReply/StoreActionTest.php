<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaReply\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_reply_for_thread_and_user_without_changing_thread(): void
    {
        $user = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $status = $thread->status;

        $reply = (new StoreAction)($user, $thread, '回答本文');

        $this->assertInstanceOf(QaReply::class, $reply);
        $this->assertSame($thread->id, $reply->qa_thread_id);
        $this->assertSame($user->id, $reply->user_id);
        $this->assertSame($status, $thread->fresh()->status);
    }
}
