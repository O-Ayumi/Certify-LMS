<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentGoal;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 個人目標の編集リクエスト。達成状態や親Enrollmentは更新対象に含めない。
 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('goal')) ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'target_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return ['title' => '目標', 'description' => '詳細', 'target_date' => '目標期日'];
    }
}
