<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\EnrollmentGoal;

/**
 * 個人目標のタイトル・詳細・目標期日を更新するAction。
 */
final class UpdateAction
{
    public function __invoke(EnrollmentGoal $goal, array $data): void
    {
        $goal->update($data);
    }
}
