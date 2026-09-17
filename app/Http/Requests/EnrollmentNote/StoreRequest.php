<?php

declare(strict_types=1);

namespace App\Http\Requests\EnrollmentNote;

use App\Models\EnrollmentNote;
use Illuminate\Foundation\Http\FormRequest;

/** 受講登録に業務メモを追加するリクエスト。認可は親Enrollment単位で判定する。 */
class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', [EnrollmentNote::class, $this->route('enrollment')]) ?? false;
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
