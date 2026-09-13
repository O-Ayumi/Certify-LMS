<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<EnrollmentGoal> */
class EnrollmentGoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'title' => '過去問を解き終える',
            'description' => '毎日少しずつ学習する',
            'target_date' => now()->addWeeks(2)->toDateString(),
            'achieved_at' => null,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => ['achieved_at' => now()]);
    }
}
