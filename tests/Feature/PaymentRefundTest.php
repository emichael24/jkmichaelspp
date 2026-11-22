<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\User;
use App\Services\PaymentRefundService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentRefundTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        config(['services.paystack.secret_key' => 'sk_test_fixture']);
        $admin = User::factory()->create();
        $admin->assignRole(Role::firstOrCreate(['name' => 'platform_admin']));
        $user = User::factory()->create();
        $course = Course::factory()->create();
        $order = Order::create(['user_id' => $user->id, 'subtotal' => 100, 'total' => 100, 'currency' => 'NGN', 'status' => 'paid', 'paid_at' => now()]);
        $payment = Payment::create(['user_id' => $user->id, 'order_id' => $order->id, 'gateway' => 'paystack', 'gateway_reference' => 'JKM-REFUND-TEST', 'gateway_response' => ['id' => 501], 'status' => 'success', 'amount' => 100, 'currency' => 'NGN', 'paid_at' => now()]);
        $enrollment = CourseEnrollment::create(['user_id' => $user->id, 'course_id' => $course->id, 'enrolled_at' => now()->subMonth(), 'status' => 'completed', 'completed_at' => now()->subDay(), 'source_order_id' => $order->id]);

        return [$admin, $user, $payment, $enrollment];
    }

    private function receipt(string $status = 'pending'): array
    {
        return ['id' => 801, 'transaction' => 501, 'amount' => 10000, 'currency' => 'NGN', 'status' => $status, 'domain' => 'test', 'customer' => ['email' => 'private@example.test']];
    }

    public function test_refund_waits_for_verified_settlement_and_preserves_completion_on_replay(): void
    {
        [$admin, $user, $payment, $enrollment] = $this->fixture();
        $completedAt = $enrollment->completed_at->toISOString();
        Http::fake(['https://api.paystack.co/refund' => Http::response(['status' => true, 'data' => $this->receipt()]), 'https://api.paystack.co/refund/801' => Http::response(['status' => true, 'data' => $this->receipt('processed')]), '*/transaction/verify/*' => Http::response(['data' => ['reference' => $payment->gateway_reference, 'amount' => 10000, 'currency' => 'NGN', 'status' => 'success']])]);
        $this->actingAs($admin)->post('/admin/billing/refund/'.$payment->id, ['reason' => 'Approved course withdrawal', 'confirmed' => true])->assertRedirect()->assertSessionHasNoErrors();
        $refund = PaymentRefund::firstOrFail();
        $this->assertSame('pending', $refund->status);
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertTrue($enrollment->fresh()->allowsAccess());
        app(PaymentRefundService::class)->request($payment, $admin, 'Duplicate click');
        Http::assertSentCount(1);
        $this->post('/admin/billing/refund/'.$payment->id.'/reconcile')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertSame('refunded', $payment->order->fresh()->status);
        $this->assertFalse($enrollment->fresh()->allowsAccess());
        $this->assertSame('completed', $enrollment->fresh()->status);
        $this->assertSame($completedAt, $enrollment->fresh()->completed_at->toISOString());
        app(PaymentRefundService::class)->reconcile($refund->fresh());
        app(PaymentService::class)->verifyPaystackWebhook(['data' => ['reference' => $payment->gateway_reference]]);
        $this->assertSame('refunded', $payment->fresh()->status);
        $this->assertDatabaseCount('payment_refunds', 1);
        $this->assertArrayNotHasKey('customer', $refund->fresh()->receipt);
        $this->assertNotContains('Approved course withdrawal', $refund->getRawOriginal());
    }

    public function test_uncertain_submission_is_not_resent_and_is_reconciled_by_transaction(): void
    {
        [$admin, , $payment] = $this->fixture();
        Http::fake(['https://api.paystack.co/refund' => Http::response([], 503), '*/transaction/verify/*' => Http::response(['status' => true, 'data' => ['reference' => $payment->gateway_reference, 'id' => 501]]), 'https://api.paystack.co/refund?*' => Http::response(['status' => true, 'data' => [$this->receipt('processed')]])]);
        $service = app(PaymentRefundService::class);
        $refund = $service->request($payment, $admin, 'Approved withdrawal');
        $this->assertSame('unknown', $refund->status);
        $service->request($payment, $admin, 'Repeated request');
        Http::assertSentCount(1);
        $service->reconcile($refund);
        $this->assertSame('processed', $refund->fresh()->status);
        Http::assertSentCount(3);
    }

    public function test_wrong_settlement_cannot_change_payment_or_access_and_independent_grants_remain(): void
    {
        [$admin, $user, $payment, $enrollment] = $this->fixture();
        $enrollment->update(['source_order_id' => null]);
        Http::fake(['https://api.paystack.co/refund' => Http::response(['status' => true, 'data' => $this->receipt()]), 'https://api.paystack.co/refund/801' => Http::sequence()->push(['status' => true, 'data' => array_replace($this->receipt('processed'), ['amount' => 1])])->push(['status' => true, 'data' => $this->receipt('processed')])]);
        app(PaymentRefundService::class)->request($payment, $admin, 'Approved withdrawal');
        $this->actingAs($admin)->postJson('/admin/billing/refund/'.$payment->id.'/reconcile')->assertUnprocessable();
        $this->assertSame('success', $payment->fresh()->status);
        $this->assertTrue($enrollment->fresh()->allowsAccess());
        $this->post('/admin/billing/refund/'.$payment->id.'/reconcile')->assertRedirect();
        $this->assertTrue($enrollment->fresh()->allowsAccess());
    }

    public function test_refund_requires_admin_confirmation_and_verified_positive_payment(): void
    {
        [$admin, $user, $payment] = $this->fixture();
        Http::fake();
        $this->actingAs($user)->postJson('/admin/billing/refund/'.$payment->id, ['confirmed' => true, 'reason' => 'Learner request'])->assertForbidden();
        $this->actingAs($admin)->postJson('/admin/billing/refund/'.$payment->id, ['reason' => 'Missing confirmation'])->assertUnprocessable();
        $payment->update(['status' => 'pending']);
        $this->postJson('/admin/billing/refund/'.$payment->id, ['reason' => 'Not yet paid', 'confirmed' => true])->assertUnprocessable();
        $this->assertDatabaseCount('payment_refunds', 0);
        Http::assertNothingSent();
    }
}
