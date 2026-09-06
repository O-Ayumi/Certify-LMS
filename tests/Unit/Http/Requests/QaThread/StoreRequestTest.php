<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\QaThread;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_submit_valid_payload(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => 'タイトル',
            'body' => '本文',
        ])->assertRedirect();
    }

    public function test_invalid_payload_and_unpublished_certification_are_rejected(): void
    {
        $student = User::factory()->student()->create();
        $archived = Certification::factory()->archived()->create();

        $this->actingAs($student)->postJson(route('qa-board.store'), [
            'certification_id' => $archived->id,
            'title' => str_repeat('a', 201),
            'body' => str_repeat('b', 5001),
        ])->assertUnprocessable()->assertJsonValidationErrors(['certification_id', 'title', 'body']);
    }

    public function test_coach_and_admin_are_not_authorized(): void
    {
        $certification = Certification::factory()->published()->create();
        foreach ([User::factory()->coach(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())
                ->postJson(route('qa-board.store'), ['certification_id' => $certification->id, 'title' => 't', 'body' => 'b'])
                ->assertForbidden();
        }
    }
}
