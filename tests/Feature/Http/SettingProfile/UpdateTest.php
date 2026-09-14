<?php

declare(strict_types=1);

namespace Tests\Feature\Http\SettingProfile;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_roles_and_graduated_students_can_update_their_profile(): void
    {
        foreach ([
            User::factory()->student(),
            User::factory()->coach(),
            User::factory()->admin(),
            User::factory()->student()->graduated(),
        ] as $factory) {
            $user = $factory->create();

            $this->actingAs($user)->patch(route('settings.profile.update'), [
                'name' => str_repeat('あ', 50),
                'bio' => str_repeat('あ', 1000),
            ])->assertRedirect(route('settings.profile.edit'))
                ->assertSessionHas('success');

            $this->assertSame(str_repeat('あ', 50), $user->fresh()->name);
            $this->assertSame(str_repeat('あ', 1000), $user->fresh()->bio);
        }
    }

    public function test_coach_can_update_and_clear_meeting_url_and_bio(): void
    {
        $user = User::factory()->coach()->create();

        $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => 'コーチ',
            'bio' => '自己紹介',
            'meeting_url' => 'http://example.com/meeting',
        ])->assertSessionHasNoErrors();

        $this->assertSame('http://example.com/meeting', $user->fresh()->meeting_url);

        $this->patch(route('settings.profile.update'), [
            'name' => 'コーチ',
            'bio' => '',
            'meeting_url' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($user->fresh()->bio);
        $this->assertNull($user->fresh()->meeting_url);
    }

    public function test_non_coaches_cannot_update_protected_fields_or_another_user(): void
    {
        $other = User::factory()->create(['name' => '別のユーザー']);

        foreach ([User::factory()->student(), User::factory()->admin()] as $factory) {
            $user = $factory->create();
            $before = $user->only(['email', 'role', 'status', 'meeting_url']);

            $this->actingAs($user)->patch(route('settings.profile.update'), [
                'name' => '変更後',
                'id' => $other->id,
                'user_id' => $other->id,
                'email' => 'invalid-email',
                'role' => 'coach',
                'status' => 'withdrawn',
                'meeting_url' => 'invalid-url',
            ])->assertSessionHasNoErrors();

            $this->assertSame($before, $user->fresh()->only(array_keys($before)));
            $this->assertSame('変更後', $user->fresh()->name);
            $this->assertSame('別のユーザー', $other->fresh()->name);
        }
    }

    public function test_invalid_inputs_are_rejected_without_updating_user(): void
    {
        $user = User::factory()->coach()->create(['name' => '変更前']);

        foreach ([
            ['name' => ''],
            ['name' => str_repeat('あ', 51)],
            ['name' => '変更後', 'bio' => str_repeat('あ', 1001)],
            ['name' => '変更後', 'meeting_url' => 'invalid-url'],
            ['name' => '変更後', 'meeting_url' => 'https://example.com/'.str_repeat('a', 500)],
        ] as $input) {
            $field = array_key_last($input);

            $this->actingAs($user)->patch(route('settings.profile.update'), $input)
                ->assertSessionHasErrors($field);

            $this->assertSame('変更前', $user->fresh()->name);
        }
    }

    public function test_guest_cannot_update_profile(): void
    {
        $this->patch(route('settings.profile.update'), ['name' => '変更後'])
            ->assertRedirect(route('login'));
    }
}
