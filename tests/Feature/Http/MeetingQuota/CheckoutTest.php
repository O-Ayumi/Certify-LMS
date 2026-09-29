<?php

declare(strict_types=1);

namespace Tests\Feature\Http\MeetingQuota;

use App\Enums\PaymentStatus;
use App\Models\MeetingPack;
use App\Models\MeetingQuotaTransaction;
use App\Models\Payment;
use App\Models\User;
use App\Services\MeetingQuotaService;
use App\Services\StripeCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Stripe\Checkout\Session;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_checkout_lists_only_published_packs(): void
    {
        $student = User::factory()->student()->create();
        $published = MeetingPack::factory()->published()->create(['name' => '公開パック']);
        MeetingPack::factory()->draft()->create(['name' => '下書きパック']);

        $this->actingAs($student)
            ->get(route('meeting-quota.checkout.select'))
            ->assertOk()
            ->assertSee('公開パック')
            ->assertDontSee('下書きパック');
    }

    public function test_only_published_packs_can_start_checkout_and_snapshot_is_kept(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create([
            'name' => '購入時パック名',
            'meeting_count' => 4,
            'price' => 9000,
        ]);
        $session = Session::constructFrom(['id' => 'cs_test_checkout', 'url' => 'https://checkout.stripe.test/session']);
        $this->mock(StripeCheckoutService::class)
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn($session);

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertRedirect($session->url);

        $payment = Payment::query()->sole();
        $this->assertSame('購入時パック名', $payment->meeting_pack_name_snapshot);
        $this->assertSame(4, $payment->quantity);
        $this->assertSame(9000, $payment->amount);
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame('cs_test_checkout', $payment->stripe_checkout_session_id);
        $this->assertSame(0, app(MeetingQuotaService::class)->remaining($student) - $student->max_meetings);

        $pack->update(['name' => '変更後の名前', 'meeting_count' => 2, 'price' => 5000]);
        $this->assertSame('購入時パック名', $payment->fresh()->meeting_pack_name_snapshot);
        $this->assertSame(4, $payment->fresh()->quantity);
        $this->assertSame(9000, $payment->fresh()->amount);
    }

    public function test_zero_price_pack_uses_the_standard_checkout_and_webhook_grant_flow(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->published()->create([
            'name' => '無料面談パック',
            'meeting_count' => 2,
            'price' => 0,
        ]);
        $session = Session::constructFrom(['id' => 'cs_test_free_checkout', 'url' => 'https://checkout.stripe.test/free-session']);
        $this->partialMock(StripeCheckoutService::class)
            ->shouldReceive('createCheckoutSession')
            ->once()
            ->withArgs(fn (Payment $payment) => $payment->amount === 0 && $payment->quantity === 2)
            ->andReturn($session);

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertRedirect($session->url);

        $payment = Payment::query()->sole();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame(0, $payment->amount);

        $payload = $this->webhookPayload($payment, 'checkout.session.completed', 'no_payment_required');
        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::query()->where('related_payment_id', $payment->id)->count());
        $this->assertSame(2, app(MeetingQuotaService::class)->remaining($student) - $student->max_meetings);
    }

    public function test_unpublished_pack_cannot_be_purchased_by_direct_post(): void
    {
        $student = User::factory()->student()->create();
        $pack = MeetingPack::factory()->draft()->create();

        $this->actingAs($student)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_non_students_and_non_learning_students_cannot_access_checkout_routes(): void
    {
        $pack = MeetingPack::factory()->published()->create();
        $users = [
            User::factory()->coach()->create(),
            User::factory()->admin()->create(),
            User::factory()->graduated()->create(),
        ];

        foreach ($users as $user) {
            $this->actingAs($user)
                ->get(route('meeting-quota.checkout.select'))
                ->assertForbidden();
            $this->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
                ->assertForbidden();
            $this->get(route('meeting-quota.success', ['session_id' => 'cs_test_foreign']))
                ->assertForbidden();
        }

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_webhook_signature_is_required_and_duplicate_success_only_grants_once(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->for($student)->create([
            'quantity' => 3,
            'amount' => 7000,
            'currency' => 'JPY',
        ]);
        $payload = json_encode([
            'id' => 'evt_test_checkout_completed',
            'object' => 'event',
            'created' => time(),
            'data' => ['object' => [
                'id' => $payment->stripe_checkout_session_id,
                'object' => 'checkout.session',
                'metadata' => ['payment_id' => $payment->id],
                'payment_status' => 'paid',
                'currency' => 'jpy',
                'amount_total' => 7000,
                'client_reference_id' => $student->id,
                'payment_intent' => 'pi_test_checkout',
            ]],
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => 'checkout.session.completed',
        ], JSON_THROW_ON_ERROR);
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't='.$timestamp.',v1=invalid',
            'CONTENT_TYPE' => 'application/json',
        ], $payload)->assertStatus(400);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->call('POST', route('webhooks.stripe'), [], [], [], [
                'HTTP_STRIPE_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ], $payload)->assertOk();
        }

        $this->assertSame(PaymentStatus::Succeeded, $payment->fresh()->status);
        $this->assertSame(1, MeetingQuotaTransaction::query()->where('related_payment_id', $payment->id)->count());
        $this->assertSame(3, app(MeetingQuotaService::class)->remaining($student) - $student->max_meetings);
    }

    public function test_unpaid_failed_and_expired_sessions_never_grant_quota(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $student = User::factory()->student()->create();
        $pendingPayment = Payment::factory()->for($student)->create([
            'stripe_checkout_session_id' => null,
            'quantity' => 2,
        ]);

        $unpaidPayload = $this->webhookPayload($pendingPayment, 'checkout.session.completed', 'unpaid');
        $this->postSignedWebhook($unpaidPayload)->assertOk();
        $this->assertSame(PaymentStatus::Pending, $pendingPayment->fresh()->status);

        $failedPayment = Payment::factory()->for($student)->create(['quantity' => 4]);
        $this->postSignedWebhook($this->webhookPayload($failedPayment, 'checkout.session.async_payment_failed', 'unpaid'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $failedPayment->fresh()->status);

        $expiredPayment = Payment::factory()->for($student)->create(['quantity' => 6]);
        $this->postSignedWebhook($this->webhookPayload($expiredPayment, 'checkout.session.expired', 'unpaid'))->assertOk();
        $this->assertSame(PaymentStatus::Failed, $expiredPayment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
        $this->assertSame(0, app(MeetingQuotaService::class)->remaining($student) - $student->max_meetings);
    }

    public function test_signed_webhook_rejects_mismatched_payment_details_and_ignores_unknown_events(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $student = User::factory()->student()->create();
        $otherStudent = User::factory()->student()->create();
        $mismatches = [
            ['amount_total' => 1],
            ['currency' => 'usd'],
            ['client_reference_id' => $otherStudent->id],
            ['payment_status' => 'no_payment_required'],
        ];

        foreach ($mismatches as $overrides) {
            $payment = Payment::factory()->for($student)->create();
            $payload = $this->webhookPayload($payment, 'checkout.session.completed', 'paid', $overrides);
            $this->postSignedWebhook($payload)->assertOk();
            $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        }

        $payment = Payment::factory()->for($student)->create();
        $this->postSignedWebhook($this->webhookPayload($payment, 'customer.created', 'paid'))->assertOk();
        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    public function test_signed_webhook_with_a_different_checkout_session_is_ignored(): void
    {
        config(['services.stripe.webhook_secret' => 'whsec_test_secret']);
        $student = User::factory()->student()->create();
        $payment = Payment::factory()->for($student)->create();
        $payload = $this->webhookPayload(
            $payment,
            'checkout.session.completed',
            'paid',
            ['id' => 'cs_test_unrelated_session'],
        );

        $this->postSignedWebhook($payload)->assertOk();

        $this->assertSame(PaymentStatus::Pending, $payment->fresh()->status);
        $this->assertDatabaseCount('meeting_quota_transactions', 0);
    }

    /** @param array<string, mixed> $overrides */
    private function webhookPayload(
        Payment $payment,
        string $type,
        string $paymentStatus,
        array $overrides = [],
    ): string {
        $session = array_replace([
            'id' => $payment->stripe_checkout_session_id ?? 'cs_test_'.fake()->uuid(),
            'object' => 'checkout.session',
            'metadata' => ['payment_id' => $payment->id],
            'payment_status' => $paymentStatus,
            'currency' => 'jpy',
            'amount_total' => $payment->amount,
            'client_reference_id' => $payment->user_id,
            'payment_intent' => null,
        ], $overrides);

        return json_encode([
            'id' => 'evt_'.fake()->uuid(),
            'object' => 'event',
            'created' => time(),
            'data' => ['object' => $session],
            'livemode' => false,
            'pending_webhooks' => 1,
            'type' => $type,
        ], JSON_THROW_ON_ERROR);
    }

    private function postSignedWebhook(string $payload): TestResponse
    {
        $timestamp = time();
        $signature = 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$payload, 'whsec_test_secret');

        return $this->call('POST', route('webhooks.stripe'), [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => $signature,
            'CONTENT_TYPE' => 'application/json',
        ], $payload);
    }
}
