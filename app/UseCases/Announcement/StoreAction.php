<?php

declare(strict_types=1);

namespace App\UseCases\Announcement;

use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use App\Services\AnnouncementRecipientService;
use Illuminate\Support\Facades\DB;

final class StoreAction
{
    public function __construct(
        private readonly AnnouncementRecipientService $recipientService,
    ) {}

    /**
     * お知らせを作成し、commit 後に受信者ごとの通知をキューへ積む。
     * 対象者0件でもお知らせは作成する。
     *
     * @param array<string, mixed> $validated
     */
    public function __invoke(User $admin, array $validated): AdminAnnouncement
    {
        $announcement = DB::transaction(function () use ($admin, $validated): AdminAnnouncement {
            $recipients = $this->recipientService->resolve($validated);

            $announcement = AdminAnnouncement::query()->create([
                'title' => $validated['title'],
                'body' => $validated['body'],
                'target_type' => $validated['target_type'],
                'target_certification_id' => $validated['target_certification_id'] ?? null,
                'target_user_id' => $validated['target_user_id'] ?? null,
                'created_by_user_id' => $admin->id,
                'dispatched_count' => $recipients->count(),
                'dispatched_at' => now(),
            ]);

            $recipientIds = $recipients->modelKeys();

            DB::afterCommit(function () use ($announcement, $recipientIds): void {
                $notification = new AdminAnnouncementNotification($announcement);

                User::query()
                    ->whereKey($recipientIds)
                    ->get()
                    ->each(fn (User $recipient) => $recipient->notify($notification));
            });

            return $announcement;
        });

        return $announcement;
    }
}
