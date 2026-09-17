<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use Illuminate\Foundation\Http\FormRequest;

/** 受講登録メモの本文を更新するリクエスト。親Enrollment等は更新対象に含めない。 */
class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('note')) ?? false;
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:2000']];
    }

    public function attributes(): array
    {
        return ['body' => 'メモ本文'];
    }
}
