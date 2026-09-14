<?php

declare(strict_types=1);

namespace App\Http\Controllers\Settings;

use App\Actions\Fortify\UpdateUserPassword;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class PasswordController extends Controller
{
    public function update(Request $request, UpdateUserPassword $action): RedirectResponse
    {
        $redirectTo = route('settings.profile.edit', ['tab' => 'password']);

        try {
            $action->update($request->user(), $request->only([
                'current_password', 'password', 'password_confirmation',
            ]));
        } catch (ValidationException $e) {
            throw $e->redirectTo($redirectTo);
        }

        return redirect()->to($redirectTo)->with('success', 'パスワードを更新しました。');
    }
}
