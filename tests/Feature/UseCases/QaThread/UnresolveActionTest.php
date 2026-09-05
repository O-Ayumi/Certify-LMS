<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\QaThread;
use App\UseCases\QaThread\UnresolveAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UnresolveActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_changes_resolved_to_open_and_clears_timestamp(): void
    {
        $thread = QaThread::factory()->resolved()->create();
        $result = (new UnresolveAction)($thread);

        $this->assertSame(QaThreadStatus::Open, $result->status);
        $this->assertNull($result->resolved_at);
        $this->assertSame(QaThreadStatus::Open, (new UnresolveAction)($result)->status);
    }
}
