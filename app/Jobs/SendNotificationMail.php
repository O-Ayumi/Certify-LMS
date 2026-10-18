<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

final class SendNotificationMail implements ShouldQueue
{
    use Dispatchable, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public readonly string $userId,
        public readonly Notification $notification,
    ) {}

    public function handle(): void
    {
        $user = User::query()->find($this->userId);
        if ($user === null || ! $user->isInProgressRecipient()) {
            return;
        }

        $user->notifyNow($this->notification, ['mail']);
    }
}
