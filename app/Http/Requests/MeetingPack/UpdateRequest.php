<?php

declare(strict_types=1);

namespace App\Http\Requests\MeetingPack;

use App\Models\MeetingPack;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRequest extends FormRequest
{
    /**
     * 面談パックのマスタ管理用更新リクエスト。管理者のみ操作可能。
     */
    public function authorize(): bool
    {
        $plan = $this->route('plan');

        return $plan instanceof MeetingPack && ($this->user()?->can('update', $plan) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'meeting_count' => ['required', 'integer', 'min:1', 'max:100'],
            'price' => ['required', 'integer', 'min:0', 'max:1000000'],
            'stripe_price_id' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'パック名',
            'description' => '説明',
            'meeting_count' => '面談回数',
            'price' => '価格',
            'stripe_price_id' => 'Stripe Price ID',
            'sort_order' => 'ソート順',
        ];
    }
}
