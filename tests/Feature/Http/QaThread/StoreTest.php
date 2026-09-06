<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_create_a_thread_with_fixed_owner_and_initial_state(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $response = $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '新しい質問',
            'body' => '質問本文です。',
            'user_id' => User::factory()->student()->create()->id,
            'status' => QaThreadStatus::Resolved->value,
        ]);

        $thread = QaThread::query()->latest('created_at')->firstOrFail();
        $response->assertRedirect(route('qa-board.show', $thread));
        $this->assertDatabaseHas('qa_threads', [
            'id' => $thread->id,
            'certification_id' => $certification->id,
            'user_id' => $student->id,
            'title' => '新しい質問',
            'body' => '質問本文です。',
            'status' => QaThreadStatus::Open->value,
            'resolved_at' => null,
        ]);
    }

    public function test_unpublished_certification_is_rejected(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->archived()->create();

        $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => '質問',
            'body' => '本文',
        ])->assertSessionHasErrors('certification_id');
    }

    public function test_coach_and_admin_cannot_create_a_thread(): void
    {
        $certification = Certification::factory()->published()->create();
        foreach ([User::factory()->coach(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())
                ->post(route('qa-board.store'), [
                    'certification_id' => $certification->id,
                    'title' => '質問',
                    'body' => '本文',
                ])
                ->assertForbidden();
        }
    }

    public function test_required_and_maximum_length_fields_are_validated(): void
    {
        $student = User::factory()->student()->create();
        $certification = Certification::factory()->published()->create();

        $this->actingAs($student)->post(route('qa-board.store'), [
            'certification_id' => $certification->id,
            'title' => str_repeat('a', 201),
            'body' => str_repeat('b', 5001),
        ])->assertSessionHasErrors(['title', 'body']);
    }
}
