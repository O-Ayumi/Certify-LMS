<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AnnouncementTargetType;
use App\Models\AdminAnnouncement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AdminAnnouncement> */
class AdminAnnouncementFactory extends Factory
{
    protected $model = AdminAnnouncement::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'body' => fake()->paragraph(),
            'target_type' => AnnouncementTargetType::AllStudents->value,
            'target_certification_id' => null,
            'target_user_id' => null,
            'created_by_user_id' => User::factory()->admin(),
            'dispatched_count' => 0,
            'dispatched_at' => now(),
        ];
    }
}
