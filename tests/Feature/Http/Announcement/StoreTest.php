<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Models\Certification;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\SendQueuedNotifications;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_zero_recipient_announcement(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => 'メンテナンスのお知らせ',
            'body' => '本文です。',
            'target_type' => AnnouncementTargetType::AllStudents->value,
        ])->assertRedirect(route('admin.announcements.index'));

        $this->assertDatabaseHas('admin_announcements', [
            'title' => 'メンテナンスのお知らせ',
            'dispatched_count' => 0,
            'created_by_user_id' => $admin->id,
        ]);
        Queue::assertNothingPushed();
    }

    public function test_active_recipients_are_queued_after_announcement_is_committed(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $students = User::factory()->count(2)->create();
        $certification = Certification::factory()->published()->create();
        foreach ($students as $student) {
            Enrollment::factory()->learning()->create([
                'user_id' => $student->id,
                'certification_id' => $certification->id,
            ]);
        }

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '資格更新',
            'body' => '教材を更新しました。',
            'target_type' => AnnouncementTargetType::Certification->value,
            'target_certification_id' => $certification->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_announcements', [
            'dispatched_count' => 2,
        ]);
        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_non_admin_cannot_create_announcement(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->post(route('admin.announcements.store'), [])
            ->assertForbidden();
    }

    public function test_user_target_dispatches_only_to_the_selected_active_student(): void
    {
        Queue::fake();
        $admin = User::factory()->admin()->create();
        $selected = User::factory()->create();
        User::factory()->graduated()->create();

        $this->actingAs($admin)->post(route('admin.announcements.store'), [
            'title' => '個別連絡',
            'body' => '本文',
            'target_type' => AnnouncementTargetType::User->value,
            'target_user_id' => $selected->id,
        ])->assertRedirect();

        $this->assertDatabaseHas('admin_announcements', ['dispatched_count' => 1]);
        Queue::assertPushed(SendQueuedNotifications::class);
    }

    public function test_store_validates_required_target_fields(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)
            ->post(route('admin.announcements.store'), [
                'title' => 'タイトル',
                'body' => '本文',
                'target_type' => AnnouncementTargetType::Certification->value,
            ])
            ->assertSessionHasErrors('target_certification_id');

        $this->assertDatabaseCount('admin_announcements', 0);
    }

    public function test_only_admin_can_open_management_pages(): void
    {
        $student = User::factory()->create();

        $this->actingAs($student)
            ->get(route('admin.announcements.index'))
            ->assertForbidden();

        $this->actingAs($student)
            ->get(route('admin.announcements.create'))
            ->assertForbidden();
    }
}
