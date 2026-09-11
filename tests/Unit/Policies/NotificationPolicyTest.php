<?php

declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Policies\NotificationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\NotificationTestHelpers;
use Tests\TestCase;

class NotificationPolicyTest extends TestCase
{
    use NotificationTestHelpers, RefreshDatabase;

    public function test_all_roles_can_view_index_regardless_of_status(): void
    {
        $policy = new NotificationPolicy;
        foreach (['student', 'coach', 'admin'] as $role) {
            foreach (['in_progress', 'invited', 'graduated', 'withdrawn'] as $status) {
                $user = User::factory()->create(['role' => $role, 'status' => $status]);
                $this->assertTrue($policy->viewAny($user));
            }
        }
    }

    public function test_only_owner_can_update_even_after_graduation(): void
    {
        $owner = User::factory()->graduated()->create();
        $notification = $this->notificationFor($owner);
        $policy = new NotificationPolicy;

        $this->assertTrue($policy->update($owner, $notification));
        $this->assertFalse($policy->update(User::factory()->create(), $notification));
        $this->assertFalse($policy->update(User::factory()->admin()->create(), $notification));

        $notification->notifiable_type = 'App\\Models\\AnotherNotifiable';
        $this->assertFalse($policy->update($owner, $notification));
    }
}
