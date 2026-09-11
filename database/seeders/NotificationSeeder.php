<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\ChatMessage;
use App\Models\Meeting;
use App\Models\QaReply;
use App\Models\User;
use App\Notifications\Chat\ChatMessageReceivedNotification;
use App\Notifications\Meeting\MeetingCanceledNotification;
use App\Notifications\Meeting\MeetingReservedNotification;
use App\Notifications\QaReply\QaReplyReceivedNotification;
use Illuminate\Database\Seeder;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * 固定アカウントに既読・未読を混在させ、25 件でページネーションを確認できるようにする。
 * 既存の業務データを遷移先に使い、メールは送らない。
 */
final class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->whereIn('email', ['student@certify-lms.test', 'coach@certify-lms.test', 'coach2@certify-lms.test'])
            ->where('status', UserStatus::InProgress)
            ->whereIn('role', [UserRole::Student->value, UserRole::Coach->value])
            ->get();

        foreach ($users as $user) {
            $samples = $this->samples($user);
            if ($samples === []) {
                continue;
            }

            for ($i = 0; $i < 25; $i++) {
                $notification = $samples[$i % count($samples)];
                $createdAt = now()->subMinutes(25 - $i);
                $user->notifications()->create([
                    'id' => (string) Str::uuid(),
                    'type' => $notification::class,
                    'data' => $notification->toArray($user),
                    'read_at' => $i % 3 === 0 ? $createdAt->copy()->addSeconds(30) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }

    /** @return array<int, Notification> */
    private function samples(User $user): array
    {
        $samples = [];
        $message = ChatMessage::query()
            ->where('sender_user_id', '!=', $user->id)
            ->whereHas('chatRoom.members', fn ($query) => $query->where('user_id', $user->id))
            ->first();
        if ($message !== null) {
            $samples[] = new ChatMessageReceivedNotification($message);
        }

        $reply = QaReply::query()
            ->where('user_id', '!=', $user->id)
            ->whereHas('thread', fn ($query) => $query->where('user_id', $user->id))
            ->first();
        if ($reply !== null) {
            $samples[] = new QaReplyReceivedNotification($reply);
        }

        $reserved = Meeting::query()->where('coach_id', $user->id)->first();
        if ($reserved !== null) {
            $samples[] = new MeetingReservedNotification($reserved);
        }

        $canceled = Meeting::query()
            ->whereNotNull('canceled_at')
            ->where('canceled_by_user_id', '!=', $user->id)
            ->where(fn ($query) => $query->where('student_id', $user->id)->orWhere('coach_id', $user->id))
            ->first();
        if ($canceled !== null) {
            $samples[] = new MeetingCanceledNotification($canceled);
        }

        return $samples;
    }
}
