<?php

declare(strict_types=1);

namespace App\Http\Requests\SettingProfile\Avatar;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 本人のアバター画像をアップロードするリクエスト。
 * PNG / JPEG / WebP の 2MB 以下に制限する。
 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => ['required', 'image', 'mimes:png,jpg,jpeg,webp', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'avatar' => 'アイコン画像',
        ];
    }
}
