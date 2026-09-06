<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Models\QaReply;
use App\Models\QaThread;
use App\UseCases\QaThread\ShowAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShowActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_eager_loads_relations_and_counts_replies_in_oldest_order(): void
    {
        $thread = QaThread::factory()->create();
        $newer = QaReply::factory()->for($thread, 'thread')->create(['created_at' => now()->addMinute()]);
        $older = QaReply::factory()->for($thread, 'thread')->create(['created_at' => now()]);

        $result = (new ShowAction)($thread);

        $this->assertTrue($result->relationLoaded('certification'));
        $this->assertTrue($result->relationLoaded('user'));
        $this->assertTrue($result->relationLoaded('replies'));
        $this->assertSame([$older->id, $newer->id], $result->replies->pluck('id')->all());
        $this->assertSame(2, $result->replies_count);
    }
}
