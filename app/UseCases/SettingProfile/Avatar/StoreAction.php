<?php

declare(strict_types=1);

namespace App\UseCases\SettingProfile\Avatar;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * 画像を保存して本人の URL を差し替える。旧画像は commit 後に削除する。
 */
final class StoreAction
{
    public function __construct(private readonly DestroyAction $destroyAction) {}

    public function __invoke(User $user, UploadedFile $file): User
    {
        $path = null;

        try {
            $path = Storage::disk('public')->putFile('avatars/'.$user->id, $file);
            if ($path === false) {
                throw new \RuntimeException('Avatar storage failed.');
            }

            return DB::transaction(function () use ($user, $path) {
                $user = User::query()->lockForUpdate()->findOrFail($user->id);
                $oldUrl = $user->avatar_url;
                $user->update(['avatar_url' => Storage::disk('public')->url($path)]);

                DB::afterCommit(fn () => $this->destroyAction->deleteFile($user, $oldUrl));

                return $user->fresh();
            });
        } catch (\Throwable $e) {
            if (is_string($path)) {
                Storage::disk('public')->delete($path);
            }
            report($e);

            throw ValidationException::withMessages(['avatar' => '画像を保存できませんでした。もう一度お試しください。']);
        }
    }
}
