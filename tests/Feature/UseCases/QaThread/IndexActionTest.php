<?php

declare(strict_types=1);

namespace Tests\Feature\UseCases\QaThread;

use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use App\UseCases\QaThread\IndexAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexActionTest extends TestCase
{
    use RefreshDatabase;

    public function test_returns_visible_filtered_paginated_threads_with_eager_loaded_data(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();
        $match = QaThread::factory()->for($certification)->create(['title' => 'match']);
        QaThread::factory()->for(Certification::factory()->archived())->create();

        $result = (new IndexAction)($student, ['keyword' => 'match']);

        $this->assertSame([$match->id], collect($result->items())->pluck('id')->all());
        $this->assertTrue($result->items()[0]->relationLoaded('certification'));
        $this->assertSame(20, $result->perPage());
    }
}
