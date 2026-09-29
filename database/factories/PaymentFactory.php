<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'meeting_pack_id' => MeetingPack::factory(),
            'meeting_pack_name_snapshot' => 'テスト面談パック',
            'quantity' => 5,
            'amount' => 12000,
            'currency' => 'JPY',
            'status' => PaymentStatus::Pending,
            'stripe_checkout_session_id' => fake()->unique()->bothify('cs_test_????????????????'),
            'stripe_payment_intent_id' => null,
            'paid_at' => null,
        ];
    }

    public function succeeded(): static
    {
        return $this->state(fn () => [
            'status' => PaymentStatus::Succeeded,
            'paid_at' => now(),
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => PaymentStatus::Failed]);
    }
}
