<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class ApiTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_guest_cannot_access_notification_api(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
    }

    public function test_user_gets_only_their_latest_twenty_notifications(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->notificationFor($user, ['created_at' => now()->subMinutes($i)]);
        }
        $this->notificationFor(User::factory()->create());

        $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk()
            ->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 21);
    }

    public function test_user_can_mark_only_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);
        $other = $this->notificationFor(User::factory()->create());

        $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()->assertJsonPath('data.is_read', true)->assertJsonPath('unread_count', 0);
        $this->actingAs($user)->postJson("/api/v1/notifications/{$other->id}/read")->assertForbidden();
    }

    public function test_mark_as_read_is_idempotent_and_read_all_is_scoped_to_user(): void
    {
        $user = User::factory()->create();
        $read = $this->notificationFor($user, ['read_at' => now()->subDay()]);
        $unread = $this->notificationFor($user);
        $other = $this->notificationFor(User::factory()->create());
        $original = $read->read_at;

        $this->actingAs($user)->postJson("/api/v1/notifications/{$read->id}/read")->assertOk();
        $this->assertTrue($read->fresh()->read_at->equalTo($original));
        $this->postJson('/api/v1/notifications/read-all')->assertOk()->assertJsonPath('unread_count', 0);
        $this->assertNotNull($unread->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }
}
