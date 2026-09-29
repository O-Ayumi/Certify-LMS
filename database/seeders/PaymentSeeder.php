<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\MeetingQuotaTransactionType;
use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Seeder;

class PaymentSeeder extends Seeder
{
    public function run(): void
    {
        $packs = MeetingPack::query()->published()->ordered()->take(3)->get();
        $fixedStudent = User::query()->where('email', 'student@certify-lms.test')->first();
        $demoStudent = User::query()->where('email', 'student-noquota@certify-lms.test')->first();

        if ($packs->count() < 3 || $fixedStudent === null || $demoStudent === null) {
            return;
        }

        $this->seedPayment($fixedStudent, $packs[0], PaymentStatus::Succeeded);
        $this->seedPayment($fixedStudent, $packs[1], PaymentStatus::Pending);
        $this->seedPayment($demoStudent, $packs[2], PaymentStatus::Failed);
    }

    private function seedPayment(User $student, MeetingPack $pack, PaymentStatus $status): void
    {
        $payment = Payment::factory()->create([
            'user_id' => $student->id,
            'meeting_pack_id' => $pack->id,
            'meeting_pack_name_snapshot' => $pack->name,
            'quantity' => $pack->meeting_count,
            'amount' => $pack->price,
            'status' => $status,
            'paid_at' => $status === PaymentStatus::Succeeded ? now()->subDays(2) : null,
        ]);

        if ($status === PaymentStatus::Succeeded) {
            MeetingQuotaTransaction::query()->create([
                'user_id' => $student->id,
                'type' => MeetingQuotaTransactionType::Purchased,
                'amount' => $payment->quantity,
                'related_payment_id' => $payment->id,
                'note' => $payment->meeting_pack_name_snapshot,
                'occurred_at' => $payment->paid_at,
            ]);
        }
    }
}
