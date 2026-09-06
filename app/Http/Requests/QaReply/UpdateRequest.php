<?php

declare(strict_types=1);

namespace App\Http\Requests\QaReply;

use App\Enums\CertificationStatus;
use App\Models\QaReply;
use App\Models\QaThread;
use App\Rules\NotBlank;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        $reply = $this->route('reply');
        $thread = $this->route('thread');
        if ($thread instanceof QaThread) {
            $thread->loadMissing('certification');
            abort_if($thread->certification->status !== CertificationStatus::Published, 404);
        }

        return $reply instanceof QaReply && ($this->user()?->can('update', $reply) ?? false);
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
