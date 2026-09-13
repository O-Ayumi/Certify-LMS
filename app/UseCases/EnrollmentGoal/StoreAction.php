<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentGoal;

use App\Models\Enrollment;
use App\Models\EnrollmentGoal;

/**
 * 受講登録に個人目標を追加するAction。
 */
final class StoreAction
{
    public function __invoke(Enrollment $enrollment, array $data): EnrollmentGoal
    {
        return $enrollment->goals()->create($data);
    }
}
