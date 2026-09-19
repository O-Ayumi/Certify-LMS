<?php

declare(strict_types=1);

namespace App\Http\Requests\Announcement;

use App\Enums\AnnouncementTargetType;
use App\Enums\UserRole;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->role === UserRole::Admin;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'target_type' => [
                'required',
                Rule::enum(AnnouncementTargetType::class),
            ],
            'target_certification_id' => [
                'nullable',
                'required_if:target_type,certification',
                'exists:certifications,id',
            ],
            'target_user_id' => [
                'nullable',
                'required_if:target_type,user',
                'exists:users,id',
            ],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '本文',
            'target_type' => '配信対象',
            'target_certification_id' => '対象資格',
            'target_user_id' => '対象受講生',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'target_certification_id.required_if' => '配信対象が「資格指定」の場合、対象資格を入力してください。',
            'target_user_id.required_if' => '配信対象が「ユーザー指定」の場合、対象受講生を入力してください。',
        ];
    }
}
