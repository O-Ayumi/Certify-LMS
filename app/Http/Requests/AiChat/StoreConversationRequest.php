<?php

declare(strict_types=1);

namespace App\Http\Requests\AiChat;

use Illuminate\Foundation\Http\FormRequest;

class StoreConversationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'content' => ['nullable', 'string', 'max:2000'],
            'section_id' => ['nullable', 'string', 'exists:sections,id'],
            'enrollment_id' => ['nullable', 'string', 'exists:enrollments,id'],
        ];
    }
}
