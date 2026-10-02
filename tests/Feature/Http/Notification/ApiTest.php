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

    public function test_csrf_cookie_endpoint_is_available_for_sanctum_spa_flow(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNoContent();
    }

    public function test_empty_response_has_expected_shape(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/v1/notifications')
            ->assertOk()
            ->assertJsonStructure(['data', 'unread_count'])
            ->assertJsonPath('data', [])
            ->assertJsonPath('unread_count', 0);
    }

    public function test_user_gets_only_their_latest_twenty_notifications(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 21; $i++) {
            $this->notificationFor($user, ['created_at' => now()->subMinutes($i)]);
        }
        $this->notificationFor(User::factory()->create());

        $response = $this->actingAs($user)->getJson('/api/v1/notifications')->assertOk();

        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'message', 'url', 'read_at',
                    'created_at', 'notification_type', 'is_read',
                ],
            ],
            'unread_count',
        ])->assertJsonCount(20, 'data')->assertJsonPath('unread_count', 21);

        $ids = $response->json('data.*.id');
        $this->assertSame(
            $user->notifications()->latest()->limit(20)->pluck('id')->all(),
            $ids,
        );
        $this->assertSame('本人宛のお知らせ', $response->json('data.0.title'));
        $this->assertSame('新しいメッセージがあります。', $response->json('data.0.message'));
        $this->assertSame('chat_message_received', $response->json('data.0.notification_type'));
        $this->assertFalse($response->json('data.0.is_read'));
    }

    public function test_user_can_mark_only_their_notification_as_read(): void
    {
        $user = User::factory()->create();
        $notification = $this->notificationFor($user);
        $other = $this->notificationFor(User::factory()->create());

        $this->actingAs($user)->postJson("/api/v1/notifications/{$notification->id}/read")
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'id', 'title', 'message', 'url', 'read_at',
                    'created_at', 'notification_type', 'is_read',
                ],
                'unread_count',
            ])
            ->assertJsonPath('data.id', (string) $notification->id)
            ->assertJsonPath('data.is_read', true)
            ->assertJsonPath('unread_count', 0);
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
        $this->postJson('/api/v1/notifications/read-all')
            ->assertOk()
            ->assertJsonPath('unread_count', 0);
        $this->assertNotNull($unread->fresh()->read_at);
        $this->assertNull($other->fresh()->read_at);
    }
}
