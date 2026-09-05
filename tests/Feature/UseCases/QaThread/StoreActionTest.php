<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\StoreAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_saves_only_expected_fields_with_open_initial_state(): void
    {
        $user = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $thread = (new StoreAction)($user, [
            'certification_id' => $certification->id,
            'title' => 'title',
            'body' => 'body',
            'status' => QaThreadStatus::Resolved->value,
            'user_id' => User::factory()->student()->create()->id,
        ]);

        $this->assertInstanceOf(QaThread::class, $thread);
        $this->assertSame($user->id, $thread->user_id);
        $this->assertSame(QaThreadStatus::Open, $thread->status);
        $this->assertNull($thread->resolved_at);
    }
}
