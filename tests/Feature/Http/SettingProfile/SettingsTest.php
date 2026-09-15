<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingProfile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use DatabaseMigrations;

    public function test_all_roles_and_graduated_students_can_view_settings_and_manage_avatar_and_password(): void
    {
        Storage::fake('public');

        foreach ([User::factory()->student(), User::factory()->coach(), User::factory()->admin(), User::factory()->graduated()] as $factory) {
            $user = $factory->create();
            $response = $this->actingAs($user)->get(route('settings.profile.edit'))
                ->assertOk()->assertSee($user->email);
            if ($user->role === UserRole::Coach) {
                $response->assertSee('name="meeting_url"', false);
            } else {
                $response->assertDontSee('name="meeting_url"', false);
            }
            $this->get(route('settings.profile.edit', ['tab' => 'password']))->assertOk();

            $this->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('avatar.png')])
                ->assertRedirect(route('settings.profile.edit'))->assertSessionHasNoErrors();
            $first = Storage::disk('public')->allFiles('avatars/'.$user->id)[0];
            $this->assertSame(Storage::disk('public')->url($first), $user->fresh()->avatar_url);

            $this->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('avatar.jpg')])
                ->assertSessionHasNoErrors();
            Storage::disk('public')->assertMissing($first);
            $this->assertCount(1, Storage::disk('public')->allFiles('avatars/'.$user->id));

            $this->delete(route('settings.avatar.destroy'))->assertRedirect(route('settings.profile.edit'));
            $this->assertNull($user->fresh()->avatar_url);
            $this->assertCount(0, Storage::disk('public')->allFiles('avatars/'.$user->id));
            $this->delete(route('settings.avatar.destroy'))->assertRedirect();

            $this->put(route('settings.password.update'), [
                'current_password' => 'password', 'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
            $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_avatar_validation_and_ownership(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $other = User::factory()->create();
        Storage::disk('public')->put('avatars/'.$other->id.'/original.png', 'original');
        $other->update(['avatar_url' => Storage::disk('public')->url('avatars/'.$other->id.'/original.png')]);
        $this->actingAs($user);

        foreach ([null, UploadedFile::fake()->image('bad.gif'), UploadedFile::fake()->create('bad.png', 1, 'text/plain'), UploadedFile::fake()->image('big.png')->size(2049)] as $file) {
            $this->from(route('settings.profile.edit'))->post(route('settings.avatar.store'), ['avatar' => $file])
                ->assertSessionHasErrors('avatar');
            $this->assertNull($user->fresh()->avatar_url);
        }

        $this->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('valid.webp')->size(2048),
            'user_id' => $other->id,
        ])->assertSessionHasNoErrors();
        $this->delete(route('settings.avatar.destroy'), ['user_id' => $other->id])->assertRedirect();
        Storage::disk('public')->assertExists('avatars/'.$other->id.'/original.png');
        $this->assertNotNull($other->fresh()->avatar_url);
    }

    public function test_avatar_delete_does_not_delete_external_or_malformed_url(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'avatar_url' => 'https://cdn.example.com/avatar.png',
        ]);

        $this->actingAs($user)->delete(route('settings.avatar.destroy'))
            ->assertRedirect(route('settings.profile.edit'));

        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertDirectoryEmpty('avatars/'.$user->id);
    }

    public function test_avatar_update_does_not_accept_a_missing_or_oversized_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $this->actingAs($user)->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), [])
            ->assertRedirect(route('settings.profile.edit'))
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->fresh()->avatar_url);
        $this->assertCount(0, Storage::disk('public')->allFiles('avatars/'.$user->id));
    }

    public function test_password_errors_return_to_password_tab_without_flashing_passwords(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        foreach ([
            ['wrong', 'new-password', 'new-password', 'current_password'],
            ['password', 'short', 'short', 'password'],
            ['password', 'new-password', 'different', 'password'],
        ] as [$current, $password, $confirmation, $field]) {
            $this->put(route('settings.password.update'), [
                'current_password' => $current, 'password' => $password,
                'password_confirmation' => $confirmation,
            ])->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
                ->assertSessionHasErrors($field, null, 'updatePassword')
                ->assertSessionMissing('_old_input.current_password')
                ->assertSessionMissing('_old_input.password')
                ->assertSessionMissing('_old_input.password_confirmation');
            $message = session('errors')->getBag('updatePassword')->first($field);
            $this->assertStringNotContainsString('The provided password', $message);
            $this->assertStringNotContainsString('current password', $message);
            $this->assertTrue(Hash::check('password', $user->fresh()->password));
        }

        $this->put(route('settings.password.update'), [
            'current_password' => 'password', 'password' => 'password', 'password_confirmation' => 'password',
        ])->assertSessionHasNoErrors();
    }

    public function test_password_requires_all_fields_and_does_not_change_hash(): void
    {
        $user = User::factory()->create();
        $originalHash = $user->password;

        $this->actingAs($user)->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [])
            ->assertRedirect(route('settings.profile.edit', ['tab' => 'password']))
            ->assertSessionHasErrors(['current_password', 'password'], null, 'updatePassword');

        $errors = session('errors')->getBag('updatePassword');
        $this->assertSame('現在のパスワードを入力してください。', $errors->first('current_password'));

        $this->assertSame($originalHash, $user->fresh()->password);
    }

    public function test_failed_avatar_database_update_preserves_old_image_and_removes_new_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $oldPath = 'avatars/'.$user->id.'/original.png';
        Storage::disk('public')->put($oldPath, 'original');
        $oldUrl = Storage::disk('public')->url($oldPath);
        $user->update(['avatar_url' => $oldUrl]);

        User::updating(function (User $updatingUser) use ($user) {
            if ($updatingUser->id === $user->id) {
                throw new \RuntimeException('Simulated database failure');
            }
        });

        $this->actingAs($user)->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), ['avatar' => UploadedFile::fake()->image('new.png')])
            ->assertSessionHasErrors('avatar');

        $this->assertSame($oldUrl, $user->fresh()->avatar_url);
        $this->assertSame([$oldPath], Storage::disk('public')->allFiles('avatars/'.$user->id));
    }

    public function test_guests_cannot_use_settings_and_fortify_email_update_is_disabled(): void
    {
        $this->get(route('settings.profile.edit'))->assertRedirect(route('login'));
        $this->post(route('settings.avatar.store'))->assertRedirect(route('login'));
        $this->delete(route('settings.avatar.destroy'))->assertRedirect(route('login'));
        $this->put(route('settings.password.update'))->assertRedirect(route('login'));

        $user = User::factory()->create();
        $email = $user->email;
        $this->actingAs($user)->put('/user/profile-information', ['name' => 'Changed', 'email' => 'changed@example.com'])->assertNotFound();
        $this->assertSame($email, $user->fresh()->email);
    }
}
