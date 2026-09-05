<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QaThreadIndexQueryCountTest extends TestCase
{
    use RefreshDatabase;

    public function test_index_query_count_does_not_grow_with_thread_count(): void
    {
        $admin = User::factory()->admin()->create();
        $certification = Certification::factory()->published()->create();
        $this->createThreads($certification, 2);

        $baseline = $this->countQueriesFor(fn () => $this->actingAs($admin)->get(route('admin.qa-board.index')));
        $this->createThreads($certification, 10);
        $scaled = $this->countQueriesFor(fn () => $this->actingAs($admin)->get(route('admin.qa-board.index')));

        $this->assertLessThanOrEqual($baseline + 3, $scaled, "質問一覧でN+1が発生しています (基準 {$baseline} -> 増加後 {$scaled})。");
    }

    private function createThreads(Certification $certification, int $count): void
    {
        $author = User::factory()->student()->create();
        $coach = User::factory()->coach()->create();
        $certification->coaches()->attach($coach->id, [
            'id' => (string) Str::ulid(),
            'assigned_by_user_id' => User::factory()->admin()->create()->id,
            'assigned_at' => now(),
        ]);
        QaThread::factory()->count($count)->for($certification)->for($author)->create()->each(
            fn (QaThread $thread) => QaReply::factory()->for($thread, 'thread')->count(2)->create()
        );
    }

    private function countQueriesFor(\Closure $closure): int
    {
        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $closure();

        return $count;
    }
}
