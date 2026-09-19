<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Support\Collection;

class AnnouncementRecipientService
{
    /**
     * お知らせの配信対象となる受講生を返す。
     *
     * - 全受講生: role=student && status=in_progress
     * - 資格指定: 上記に加えて対象資格のEnrollmentがlearning
     * - ユーザー指定: 上記条件を満たす指定ユーザー
     *
     * @param array{
     *     target_type: string,
     *     target_certification_id?: string|null,
     *     target_user_id?: string|null
     * } $validated
     *
     * @return Collection<int, User>
     */
    public function resolve(array $validated): Collection
    {
        $targetType = AnnouncementTargetType::from($validated['target_type']);

        return match ($targetType) {
            AnnouncementTargetType::AllStudents => $this->allStudents(),
            AnnouncementTargetType::Certification => $this->forCertification(
                $validated['target_certification_id'],
            ),
            AnnouncementTargetType::User => $this->forUser(
                $validated['target_user_id'],
            ),
        };
    }

    /**
     * @return Collection<int, User>
     */
    private function allStudents(): Collection
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->get();
    }

    /**
     * @param string $certificationId
     *
     * @return Collection<int, User>
     */
    private function forCertification(string $certificationId): Collection
    {
        return User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->whereHas('enrollments', function ($query) use ($certificationId): void {
                $query
                    ->where('certification_id', $certificationId)
                    ->where('status', EnrollmentStatus::Learning->value);
            })
            ->get();
    }

    /**
     * @param string $userId
     *
     * @return Collection<int, User>
     */
    private function forUser(string $userId): Collection
    {
        return User::query()
            ->whereKey($userId)
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->get();
    }
}
