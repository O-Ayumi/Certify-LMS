<?php

declare(strict_types=1);

namespace Tests\Feature\Http\QaThread;

use App\Models\Certification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_create_form_with_published_certifications_only(): void
    {
        $student = User::factory()->student()->create();
        $published = Certification::factory()->published()->create(['name' => '公開資格']);
        $archived = Certification::factory()->archived()->create(['name' => 'アーカイブ資格']);

        $response = $this->actingAs($student)->get(route('qa-board.create'));

        $response->assertOk()->assertViewIs('qa-thread.create')->assertSee('公開資格')->assertDontSee('アーカイブ資格');
        unset($published, $archived);
    }

    public function test_coach_and_admin_cannot_view_create_form(): void
    {
        foreach ([User::factory()->coach(), User::factory()->admin()] as $factory) {
            $this->actingAs($factory->create())
                ->get(route('qa-board.create'))
                ->assertForbidden();
        }
    }
}
