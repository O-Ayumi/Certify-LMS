<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_reply_owner_can_submit_valid_update(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($student)->create();

        $this->actingAs($student)->patchJson(route('qa-board.replies.update', [$thread, $reply]), ['body' => '更新'])
            ->assertRedirect();
    }

    public function test_invalid_body_and_non_owner_are_rejected(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();
        $reply = QaReply::factory()->for($thread, 'thread')->for($student)->create();

        $this->actingAs($student)->patchJson(route('qa-board.replies.update', [$thread, $reply]), ['body' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->actingAs(User::factory()->student()->create())
            ->patchJson(route('qa-board.replies.update', [$thread, $reply]), ['body' => '拒否'])
            ->assertForbidden();
    }
}
