<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_submit_valid_update(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();

        $this->actingAs($owner)->patchJson(route('qa-board.update', $thread), [
            'title' => '更新タイトル',
            'body' => '更新本文',
        ])->assertRedirect();
    }

    public function test_invalid_update_and_non_owner_are_rejected(): void
    {
        $owner = User::factory()->student()->create();
        $thread = QaThread::factory()->for($owner)->create();
        $this->actingAs($owner)->patchJson(route('qa-board.update', $thread), [
            'title' => str_repeat('a', 201),
            'body' => str_repeat('b', 5001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['title', 'body']);

        $this->actingAs(User::factory()->student()->create())
            ->patchJson(route('qa-board.update', $thread), ['title' => 'x', 'body' => 'y'])
            ->assertForbidden();
    }
}
