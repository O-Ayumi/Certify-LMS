<?php

declare(strict_types=1);

namespace App\UseCases\EnrollmentNote;

use App\Models\EnrollmentNote;

/** メモ本文だけを更新するAction。 */
final class UpdateAction
{
    public function __invoke(EnrollmentNote $note, array $data): void
    {
        $note->update(['body' => $data['body']]);
    }
}
