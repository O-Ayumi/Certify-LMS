<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\UseCases\QaThread\ResolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ResolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_open_to_resolved_and_sets_timestamp(): void
    {
        $thread = QaThread::factory()->create();
        $result = (new ResolveAction)($thread);

        $this->assertSame(QaThreadStatus::Resolved, $result->status);
        $this->assertNotNull($result->resolved_at);
        $timestamp = $result->resolved_at;
        $this->assertSame($timestamp->toDateTimeString(), (new ResolveAction)($result)->resolved_at->toDateTimeString());
    }
}
