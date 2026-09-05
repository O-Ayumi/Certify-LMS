<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class QaReplyTest extends TestCase
{
    use RefreshDatabase;

    public function test_thread_and_user_relations_return_parents(): void
    {
        $thread = QaThread::factory()->create();
        $user = User::factory()->student()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($user)->create();

        $this->assertTrue($reply->thread->is($thread));
        $this->assertTrue($reply->user->is($user));
        $this->assertNotEmpty($reply->id);
        $this->assertArrayNotHasKey('deleted_at', $reply->getAttributes());
    }

    public function test_cascade_deletes_replies_with_thread(): void
    {
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        $thread->delete();

        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
    }
}
