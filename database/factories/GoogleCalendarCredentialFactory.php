<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarCredential;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GoogleCalendarCredential> */
final class GoogleCalendarCredentialFactory extends Factory
{
    protected $model = GoogleCalendarCredential::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->coach(),
            'google_user_id' => fake()->uuid(),
            'calendar_id' => 'primary',
            'access_token' => json_encode(['access_token' => 'testing-token']),
            'refresh_token' => 'testing-refresh-token',
            'expires_at' => now()->addHour(),
            'connected_at' => now(),
        ];
    }
}
