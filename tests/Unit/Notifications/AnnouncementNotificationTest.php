<?php

declare(strict_types=1);

namespace Tests\Unit\Notifications;

use App\Models\AdminAnnouncement;
use App\Models\User;
use App\Notifications\Announcement\AdminAnnouncementNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnnouncementNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_uses_database_and_mail_and_contains_full_announcement_body(): void
    {
        $user = User::factory()->create();
        $announcement = AdminAnnouncement::query()->create([
            'title' => '重要なお知らせ',
            'body' => '本文全文です。',
            'target_type' => 'all_students',
            'created_by_user_id' => $user->id,
            'dispatched_count' => 1,
            'dispatched_at' => now(),
        ]);
        $notification = new AdminAnnouncementNotification($announcement);

        $data = $notification->toArray($user);
        $mail = $notification->toMail($user);

        $this->assertSame(['database', 'mail'], $notification->via($user));
        $this->assertSame('admin_announcement', $data['notification_type']);
        $this->assertSame('重要なお知らせ', $data['title']);
        $this->assertSame('本文全文です。', $data['body']);
        $this->assertSame('【Certify LMS】重要なお知らせ', $mail->subject);
        $this->assertContains('本文全文です。', $mail->introLines);
    }
}
