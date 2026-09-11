<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class MarkAllAsReadTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_marks_all_pages_read_without_changing_other_users_or_existing_read_dates(): void
    {
        $user = User::factory()->graduated()->create();
        $other = $this->notificationFor(User::factory()->create());
        $read = $this->notificationFor($user, ['read_at' => now()->subDay()]);
        $readAt = $read->read_at;
        for ($i = 0; $i < 21; $i++) {
            $this->notificationFor($user);
        }

        $this->actingAs($user)->post(route('notifications.markAllAsRead'))
            ->assertRedirect(route('notifications.index'))->assertSessionHas('success');
        $this->assertSame(0, $user->unreadNotifications()->count());
        $this->assertSame(22, $user->notifications()->count());
        $this->assertNull($other->fresh()->read_at);
        $this->assertTrue($read->fresh()->read_at->equalTo($readAt));

        $this->post(route('notifications.markAllAsRead'))->assertRedirect();
        $this->assertTrue($read->fresh()->read_at->equalTo($readAt));
    }

    public function test_admin_with_no_notifications_can_mark_all_read(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->post(route('notifications.markAllAsRead'))->assertRedirect(route('notifications.index'));
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_guest_cannot_mark_all_read(): void
    {
        $notification = $this->notificationFor(User::factory()->create());
        $this->post(route('notifications.markAllAsRead'))->assertRedirect(route('login'));
        $this->assertNull($notification->fresh()->read_at);
    }
}
