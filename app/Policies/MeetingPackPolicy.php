<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\MeetingPackStatus;
use App\Enums\UserRole;
use App\Models\MeetingPack;
use App\Models\User;

class MeetingPackPolicy
{
    /**
     * 面談パックのマスタ管理用ポリシー。管理者のみ操作可能。
     */
    public function viewAny(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function view(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function create(User $user): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function update(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin;
    }

    public function delete(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status !== MeetingPackStatus::Published;
    }

    public function publish(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Draft;
    }

    public function archive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Published;
    }

    public function unarchive(User $user, MeetingPack $meetingPack): bool
    {
        return $user->role === UserRole::Admin && $meetingPack->status === MeetingPackStatus::Archived;
    }
}
