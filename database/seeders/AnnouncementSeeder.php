<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\AnnouncementTargetType;
use App\Enums\EnrollmentStatus;
use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\AdminAnnouncement;
use App\Models\Certification;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/** S-B-08 の管理者お知らせ3種類と、確認用の受信通知を作成する。 */
final class AnnouncementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('role', UserRole::Admin->value)->first();
        $student = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->first();
        $certification = Certification::query()->first();

        if ($admin === null || $student === null || $certification === null) {
            return;
        }

        $this->createAnnouncement($admin, AnnouncementTargetType::AllStudents, null, null, '全受講生向けのお知らせ');
        $this->createAnnouncement($admin, AnnouncementTargetType::Certification, $certification->id, null, '資格指定のお知らせ');
        $this->createAnnouncement($admin, AnnouncementTargetType::User, null, $student->id, 'ユーザー指定のお知らせ');
    }

    private function createAnnouncement(
        User $admin,
        AnnouncementTargetType $targetType,
        ?string $certificationId,
        ?string $userId,
        string $title,
    ): void {
        $recipients = User::query()
            ->where('role', UserRole::Student->value)
            ->where('status', UserStatus::InProgress->value)
            ->when($targetType === AnnouncementTargetType::Certification, fn ($query) => $query->whereHas(
                'enrollments',
                fn ($enrollments) => $enrollments
                    ->where('certification_id', $certificationId)
                    ->where('status', EnrollmentStatus::Learning->value),
            ))
            ->when($targetType === AnnouncementTargetType::User, fn ($query) => $query->whereKey($userId))
            ->get();

        $announcement = AdminAnnouncement::create([
            'title' => $title,
            'body' => 'S-B-08 の動作確認用お知らせです。',
            'target_type' => $targetType,
            'target_certification_id' => $certificationId,
            'target_user_id' => $userId,
            'created_by_user_id' => $admin->id,
            'dispatched_count' => $recipients->count(),
            'dispatched_at' => now(),
        ]);

        $notification = new AdminAnnouncementNotification($announcement);
        foreach ($recipients as $recipient) {
            $recipient->notifications()->create([
                'id' => (string) Str::uuid(),
                'type' => $notification::class,
                'data' => $notification->toArray($recipient),
            ]);
        }
    }
}
