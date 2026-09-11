<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\ChatMember;
use App\Models\ChatRoom;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class MarkAsReadTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_marks_own_notification_read_before_redirect_without_changing_chat_read_state(): void
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $member = ChatMember::factory()->create(['user_id' => $user->id, 'chat_room_id' => $room->id, 'last_read_at' => null]);
        $notification = $this->notificationFor($user, ['data' => [
            'url' => route('chat.show', $room),
        ]]);
        $this->actingAs($user)->post(route('notifications.markAsRead', $notification))
            ->assertRedirect(route('chat.show', $room));
        $readAt = $notification->fresh()->read_at;
        $this->assertNotNull($readAt);
        $this->assertNull($member->fresh()->last_read_at);

        $this->travel(5)->minutes();
        $this->post(route('notifications.markAsRead', $notification))->assertRedirect();
        $this->assertTrue($notification->fresh()->read_at->equalTo($readAt));
    }

    public function test_foreign_missing_and_malformed_ids_are_rejected_without_changes(): void
    {
        $owner = User::factory()->create();
        $notification = $this->notificationFor($owner);
        $before = $notification->fresh()->getRawOriginal();
        $this->actingAs(User::factory()->create());

        $this->post(route('notifications.markAsRead', $notification))->assertForbidden();

        foreach ([(string) Str::uuid(), 'invalid'] as $id) {
            $this->post(route('notifications.markAsRead', $id))->assertNotFound();
        }
        $this->assertSame($before, $notification->fresh()->getRawOriginal());
    }

    public function test_old_notification_remains_readable_and_missing_destination_uses_existing_404(): void
    {
        $user = User::factory()->graduated()->create();
        $notification = $this->notificationFor($user, ['data' => [
            'url' => route('meetings.show', (string) Str::ulid()),
        ]]);
        $this->actingAs($user)->post(route('notifications.markAsRead', $notification))
            ->assertRedirect($notification->data['url']);
        $this->assertNotNull($notification->fresh()->read_at);
        $this->get($notification->data['url'])->assertNotFound();
    }

    public function test_destination_keeps_its_existing_authorization(): void
    {
        $user = User::factory()->create();
        $room = ChatRoom::factory()->create();
        $notification = $this->notificationFor($user, ['data' => ['url' => route('chat.show', $room)]]);
        $this->actingAs($user)->post(route('notifications.markAsRead', $notification))->assertRedirect();
        $this->assertNotNull($notification->fresh()->read_at);
        $this->get($notification->data['url'])->assertForbidden();
    }

    public function test_guest_and_get_request_cannot_mark_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);
        $this->post(route('notifications.markAsRead', $notification))->assertRedirect(route('login'));
        $this->actingAs($user)->get(route('notifications.markAsRead', $notification))->assertStatus(405);
        $this->assertNull($notification->fresh()->read_at);
    }
}
