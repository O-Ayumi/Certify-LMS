<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_demo_users_have_roles_statuses_and_real_optional_avatars(): void
    {
        Storage::fake('public');

        $this->seed(UserSeeder::class);

        $this->assertSame(20, User::withTrashed()->count());
        $this->assertTrue(User::where('role', UserRole::Admin)->exists());

        foreach ([
            [UserRole::Coach, UserStatus::InProgress],
            [UserRole::Student, UserStatus::InProgress],
            [UserRole::Student, UserStatus::Graduated],
        ] as [$role, $status]) {
            $users = User::where('role', $role)->where('status', $status)->get();
            $this->assertTrue($users->contains(fn (User $user) => $user->avatar_url === null));
            $withAvatar = $users->filter(fn (User $user) => $user->avatar_url !== null);
            $this->assertCount(1, $withAvatar);

            $user = $withAvatar->first();
            $path = 'avatars/'.$user->id.'/demo.png';
            Storage::disk('public')->assertExists($path);
            $this->assertSame(Storage::disk('public')->url($path), $user->avatar_url);
            $this->assertSame('image/png', getimagesizefromstring(Storage::disk('public')->get($path))['mime']);
        }
    }
}
