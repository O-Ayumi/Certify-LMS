<?php

declare(strict_types=1);

namespace App\UseCases\SettingProfile\Avatar;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * 本人のアバターを解除し、commit 後に管理対象の画像だけを削除する。
 */
final class DestroyAction
{
    public function __invoke(User $user): void
    {
        DB::transaction(function () use ($user) {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            $oldUrl = $user->avatar_url;
            $user->update(['avatar_url' => null]);

            DB::afterCommit(fn () => $this->deleteFile($user, $oldUrl));
        });
    }

    /**
     * 外部 URL や他人の画像を削除対象にしない。
     */
    public function deleteFile(User $user, ?string $url): void
    {
        $directory = 'avatars/'.$user->id.'/';
        $prefix = Storage::disk('public')->url($directory);

        if ($url === null || ! str_starts_with($url, $prefix)) {
            return;
        }

        $filename = substr($url, strlen($prefix));
        if (preg_match('/\A[a-zA-Z0-9]+\.(png|jpg|jpeg|webp)\z/', $filename) !== 1) {
            return;
        }

        // DB の commit 後の削除失敗で、確定済みの画像更新を失敗扱いにしない。
        try {
            if (! Storage::disk('public')->delete($directory.$filename)) {
                report(new \RuntimeException('Avatar deletion failed.'));
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
