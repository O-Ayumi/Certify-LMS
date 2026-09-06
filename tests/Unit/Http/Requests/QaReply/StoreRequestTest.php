<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaReply;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_valid_reply(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)->postJson(route('qa-board.replies.store', $thread), ['body' => '回答'])
            ->assertRedirect();
    }

    public function test_invalid_body_is_rejected(): void
    {
        $student = User::factory()->student()->create();
        $thread = QaThread::factory()->create();

        $this->actingAs($student)->postJson(route('qa-board.replies.store', $thread), ['body' => str_repeat('a', 5001)])
            ->assertUnprocessable()->assertJsonValidationErrors('body');
    }

    public function test_admin_cannot_submit_reply(): void
    {
        $thread = QaThread::factory()->create();
        $this->actingAs(User::factory()->admin()->create())
            ->postJson(route('qa-board.replies.store', $thread), ['body' => '回答'])
            ->assertForbidden();
    }
}
