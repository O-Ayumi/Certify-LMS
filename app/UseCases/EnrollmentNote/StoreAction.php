<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\Enrollment;
use App\Models\EnrollmentNote;
use App\Models\User;

/** 認証済みユーザーを作成者として、受講登録にメモを追加するAction。 */
final class StoreAction
{
    public function __invoke(Enrollment $enrollment, User $author, array $data): EnrollmentNote
    {
        return $enrollment->notes()->create(['body' => $data['body'], 'author_user_id' => $author->id]);
    }
}
