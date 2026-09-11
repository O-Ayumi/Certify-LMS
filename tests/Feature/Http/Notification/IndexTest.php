<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Notification;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class IndexTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_index_lists_only_own_notifications_newest_first_and_counts_all_unread(): void
    {
        $user = User::factory()->create();
        $old = $this->notificationFor($user, ['created_at' => now()->subDay(), 'read_at' => now()]);
        $new = $this->notificationFor($user);
        $this->notificationFor(User::factory()->create());

        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()->assertViewIs('notifications.index')
            ->assertViewHas('tab', 'all')->assertViewHas('unreadCount', 1)
            ->assertViewHas('notifications', fn ($rows) => $rows->pluck('id')->all() === [$new->id, $old->id]);
    }

    public function test_pagination_boundaries_and_unread_filter_preserve_query(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('notifications.index'))
            ->assertOk()->assertSee('通知はありません');

        for ($i = 0; $i < 20; $i++) {
            $this->notificationFor($user);
        }
        $this->get(route('notifications.index'))->assertViewHas('notifications',
            fn ($rows) => $rows->count() === 20 && ! $rows->hasMorePages());

        $this->notificationFor($user);
        $this->notificationFor($user, ['read_at' => now()]);
        $this->get(route('notifications.index', ['tab' => 'unread']))
            ->assertOk()->assertViewHas('unreadCount', 21)
            ->assertViewHas('notifications', function ($rows) {
                $this->assertSame(21, $rows->total());
                $this->assertCount(20, $rows);
                $this->assertStringContainsString('tab=unread', $rows->nextPageUrl());

                return true;
            });
        $this->get(route('notifications.index', ['tab' => 'unread', 'page' => 2]))
            ->assertOk()->assertViewHas('notifications', fn ($rows) => $rows->count() === 1);
    }

    public function test_authenticated_admin_and_former_students_can_open_index(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get(route('notifications.index'))
            ->assertOk()->assertViewHas('unreadCount', 0)
            ->assertViewHas('notifications', fn ($rows) => $rows->isEmpty());

        foreach (['graduated', 'withdrawn'] as $status) {
            $user = User::factory()->create(['status' => $status]);
            $this->notificationFor($user);
            $this->actingAs($user)->get(route('notifications.index'))
                ->assertOk()->assertViewHas('unreadCount', 1);
        }
        $this->actingAs(User::factory()->coach()->create())
            ->get(route('notifications.index'))->assertOk();
    }

    public function test_empty_tab_defaults_to_all_and_invalid_filters_are_rejected(): void
    {
        $this->actingAs(User::factory()->create());
        $this->get(route('notifications.index', ['tab' => '']))
            ->assertOk()->assertViewHas('tab', 'all');

        foreach ([
            ['tab' => 'invalid'], ['tab' => ['unread']],
            ['page' => 0], ['page' => -1], ['page' => 1.5], ['page' => ['1']],
        ] as $query) {
            $this->getJson(route('notifications.index', $query))
                ->assertUnprocessable()->assertJsonValidationErrors(array_key_first($query));
        }
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('notifications.index'))->assertRedirect(route('login'));
    }
}
