<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class IndexRequest extends FormRequest
{
    /**
     * 面談パックのマスタ管理用一覧リクエスト。管理者のみ操作可能。
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', MeetingPack::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,published,archived'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'keyword' => 'キーワード',
            'status' => 'ステータス',
        ];
    }
}
