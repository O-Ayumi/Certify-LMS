<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\UpdateAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_title_and_body_only(): void
    {
        $user = User::factory()->student()->create();
        $thread = QaThread::factory()->for($user)->create();
        $certificationId = $thread->certification_id;

        (new UpdateAction)($thread, ['title' => 'new title', 'body' => 'new body', 'user_id' => 'bad', 'status' => QaThreadStatus::Resolved->value]);
        $fresh = $thread->fresh();

        $this->assertSame('new title', $fresh->title);
        $this->assertSame('new body', $fresh->body);
        $this->assertSame($user->id, $fresh->user_id);
        $this->assertSame($certificationId, $fresh->certification_id);
        $this->assertSame(QaThreadStatus::Open, $fresh->status);
    }
}
