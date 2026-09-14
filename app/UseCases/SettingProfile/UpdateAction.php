<?php

declare(strict_types=1);

namespace App\UseCases\SettingProfile;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * 本人の氏名・自己紹介と、コーチの固定面談 URL を更新するユースケース。
 * メール・ロール・アカウント状態は更新しない。
 */
final class UpdateAction
{
    /**
     * @param array{name: string, bio?: ?string, meeting_url?: ?string} $validated
     */
    public function __invoke(User $user, array $validated): User
    {
        return DB::transaction(function () use ($user, $validated) {
            $attributes = [
                'name' => $validated['name'],
                'bio' => $validated['bio'] ?? null,
            ];

            if ($user->role === UserRole::Coach) {
                $attributes['meeting_url'] = $validated['meeting_url'] ?? null;
            }

            $user->update($attributes);

            return $user->fresh();
        });
    }
}
