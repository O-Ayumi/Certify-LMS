<?php

declare(strict_types=1);

namespace App\Http\Requests\Notification;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Notifications\DatabaseNotification;

class IndexRequest extends FormRequest
{
    /**
     * Notification 一覧アクセスの入力検証。
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', DatabaseNotification::class) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tab' => ['nullable', 'string', 'in:all,unread'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
