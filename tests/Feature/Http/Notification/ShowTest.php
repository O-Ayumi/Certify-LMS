<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class ShowTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_owner_can_view_and_read_notification(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user, ['data' => [
            'notification_type' => 'admin_announcement',
            'title' => 'お知らせ',
            'body' => '本文全文',
            'message' => '本文全文',
        ]]);

        $this->actingAs($user)
            ->get(route('notifications.show', $notification))
            ->assertOk()
            ->assertSee('本文全文');

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_foreign_notification_is_forbidden(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $notification = $this->notificationFor($owner);

        $this->actingAs($other)
            ->get(route('notifications.show', $notification))
            ->assertForbidden();
    }
}
