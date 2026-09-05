<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IndexRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_and_valid_filters_are_accepted(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($student)->getJson(route('qa-board.index'))->assertOk();
        $this->getJson(route('qa-board.index', [
            'certification_id' => $certification->id,
            'status' => 'resolved',
            'keyword' => 'keyword',
            'page' => 1,
        ]))->assertOk();
    }

    public function test_invalid_filter_values_are_rejected_with_422(): void
    {
        $student = User::factory()->student()->create();
        foreach ([
            ['status' => 'unknown'],
            ['certification_id' => 'not-ulid'],
            ['keyword' => str_repeat('a', 101)],
            ['page' => 0],
        ] as $payload) {
            $this->actingAs($student)
                ->getJson(route('qa-board.index', $payload))
                ->assertUnprocessable();
        }
    }
}
