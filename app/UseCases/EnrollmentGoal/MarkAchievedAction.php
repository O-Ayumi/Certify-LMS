<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標を達成済みにするAction。
 */
final class MarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        EnrollmentGoal::whereKey($goal->id)->update(['achieved_at' => now()]);
    }
}
