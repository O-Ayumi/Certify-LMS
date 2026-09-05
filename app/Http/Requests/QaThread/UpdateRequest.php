<?php

declare(strict_types=1);

namespace App\Http\Requests\QaThread;

use App\Models\QaThread;
use App\Rules\NotBlank;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');

        return $thread instanceof QaThread && ($this->user()?->can('update', $thread) ?? false);
    }

    public function rules(): array
    {
        return ['title' => ['required', 'string', 'max:200', new NotBlank], 'body' => ['required', 'string', 'max:5000', new NotBlank]];
    }

    public function attributes(): array
    {
        return ['title' => 'タイトル', 'body' => '質問本文'];
    }
}
