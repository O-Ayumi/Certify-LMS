<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaReply\DestroyAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DestroyActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_deletes_reply_without_deleting_parent_thread(): void
    {
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->create();

        (new DestroyAction)($reply);

        $this->assertDatabaseMissing('qa_replies', ['id' => $reply->id]);
        $this->assertDatabaseHas('qa_threads', ['id' => $thread->id]);
    }
}
