<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標を未達成に戻すAction。
 */
final class UnmarkAchievedAction
{
    public function __invoke(EnrollmentGoal $goal): void
    {
        EnrollmentGoal::whereKey($goal->id)->whereNotNull('achieved_at')->update(['achieved_at' => null]);
    }
}
