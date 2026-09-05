<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class QaThreadTest extends TestCase
{
    use RefreshDatabase;

    public function test_relations_casts_and_reply_order_are_defined(): void
    {
        $user = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $thread = QaThread::factory()->for($certification)->for($user)->resolved()->create();
        $newer = QaReply::factory()->for($thread, 'thread')->create(['created_at' => now()->addMinute()]);
        $older = QaReply::factory()->for($thread, 'thread')->create(['created_at' => now()]);
        $fresh = $thread->fresh();

        $this->assertTrue($fresh->certification->is($certification));
        $this->assertTrue($fresh->user->is($user));
        $this->assertSame(QaThreadStatus::Resolved, $fresh->status);
        $this->assertInstanceOf(Carbon::class, $fresh->resolved_at);
        $this->assertSame([$older->id, $newer->id], $fresh->replies->pluck('id')->all());
    }

    public function test_visibility_filters_and_scopes_apply(): void
    {
        $student = User::factory()->student()->create();
        $admin = User::factory()->admin()->create();
        $published = Certification::factory()->published()->create();
        $archived = Certification::factory()->archived()->create();
        $visible = QaThread::factory()->for($published)->create(['title' => 'needle', 'body' => 'body']);
        QaThread::factory()->for($archived)->create(['title' => 'hidden']);
        QaThread::factory()->for($published)->resolved()->create(['title' => 'resolved']);

        $studentRows = QaThread::query()->visibleTo($student)->get();
        $this->assertTrue($studentRows->contains($visible));
        $this->assertCount(2, $studentRows);
        $this->assertCount(3, QaThread::query()->visibleTo($admin)->get());
        $this->assertTrue(QaThread::query()->forCertification($published->id)->get()->contains($visible));
        $this->assertSame(1, QaThread::query()->forStatus('resolved')->count());
        $this->assertTrue(QaThread::query()->keyword('needle')->get()->contains($visible));
        $this->assertSame($visible->id, QaThread::query()->newest()->first()->id);
    }

    public function test_ulid_is_generated_and_soft_deletes_are_not_used(): void
    {
        $thread = QaThread::factory()->create();

        $this->assertNotEmpty($thread->id);
        $this->assertArrayNotHasKey('deleted_at', $thread->getAttributes());
    }
}
