<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\SettingProfile\Avatar\StoreRequest;
use App\UseCases\SettingProfile\Avatar\DestroyAction;
use App\UseCases\SettingProfile\Avatar\StoreAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AvatarController extends Controller
{
    public function store(StoreRequest $request, StoreAction $action): RedirectResponse
    {
        $action($request->user(), $request->file('avatar'));

        return redirect()->route('settings.profile.edit')->with('success', 'アイコン画像を更新しました。');
    }

    public function destroy(Request $request, DestroyAction $action): RedirectResponse
    {
        $action($request->user());

        return redirect()->route('settings.profile.edit')->with('success', 'アイコン画像を削除しました。');
    }
}
