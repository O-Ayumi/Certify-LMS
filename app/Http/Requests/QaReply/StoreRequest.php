<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Enums\CertificationStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Rules\NotBlank;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        $thread = $this->route('thread');
        if ($thread instanceof QaThread) {
            $thread->loadMissing('certification');
            abort_if($thread->certification->status !== CertificationStatus::Published, 404);
        }

        return $thread instanceof QaThread && ($this->user()?->can('create', [QaReply::class, $thread]) ?? false);
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'max:5000', new NotBlank]];
    }

    public function attributes(): array
    {
        return ['body' => '回答本文'];
    }
}
