<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use App\Services\PaystackService;
use App\Services\PaystackSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PaymentSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        Role::findOrCreate('platform_admin', 'web');
        return tap(User::factory()->create(), fn ($user) => $user->assignRole('platform_admin'));
    }

    private function data(array $changes = []): array
    {
        return array_replace(['mode' => 'live', 'version' => 0, 'paystack_public_key' => 'pk_live_'.str_repeat('p', 40),
            'paystack_secret_key' => 'sk_live_'.str_repeat('s', 40), 'activate' => true, 'confirm_live' => true, 'current_password' => 'password'], $changes);
    }

    private function environment(): void
    {
        config(['services.paystack.secret_key' => 'sk_test_'.str_repeat('t', 40), 'services.paystack.public_key' => 'pk_test_'.str_repeat('u', 40)]);
    }

    public function test_only_active_platform_admins_can_access_and_change_payment_settings(): void
    {
        $this->get('/admin/settings/payments')->assertRedirect('/login');
        Role::findOrCreate('org_admin', 'web');
        $organizationAdmin = User::factory()->create();
        $organizationAdmin->assignRole('org_admin');
        $this->actingAs($organizationAdmin)->getJson('/admin/settings/payments')->assertForbidden();
        $this->putJson('/admin/settings/payments', $this->data())->assertForbidden();
        $this->actingAs(User::factory()->create())->putJson('/admin/settings/payments', $this->data())->assertForbidden();
        $admin = $this->admin(); $admin->update(['is_active' => false]);
        $this->actingAs($admin)->putJson('/admin/settings/payments', $this->data())->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_live_activation_requires_password_matching_keys_and_explicit_confirmation(): void
    {
        $this->environment(); $this->actingAs($this->admin());
        $this->putJson('/admin/settings/payments', $this->data(['current_password' => 'wrong']))->assertUnprocessable()->assertJsonValidationErrors('current_password');
        $this->putJson('/admin/settings/payments', $this->data(['paystack_public_key' => 'pk_test_'.str_repeat('p', 40)]))->assertUnprocessable()->assertJsonValidationErrors('paystack_public_key');
        $this->putJson('/admin/settings/payments', $this->data(['confirm_live' => false]))->assertUnprocessable()->assertJsonValidationErrors('confirm_live');
        $this->assertSame('test', app(PaystackSettings::class)->mode());
        Http::assertNothingSent();
    }

    public function test_keys_are_encrypted_redacted_and_can_be_saved_before_activation(): void
    {
        $this->environment(); $this->actingAs($this->admin());
        Http::fake(['https://api.paystack.co/balance' => Http::response(['status' => true, 'data' => []])]);
        $this->put('/admin/settings/payments', $this->data(['activate' => false]))->assertRedirect('/admin/settings/payments')->assertSessionHasNoErrors();
        $this->assertSame('test', app(PaystackSettings::class)->mode());
        $stored = DB::table('payment_gateway_settings')->value('credentials');
        $audit = AuditLog::where('action', 'payment_settings.updated')->firstOrFail()->toJson();
        foreach (['paystack_secret_key', 'paystack_public_key'] as $field) {
            $this->assertStringNotContainsString($this->data()[$field], $stored.$audit.json_encode(PaymentGatewaySetting::first()->toArray()));
        }
        $this->get('/admin/settings/payments')->assertInertia(fn (Assert $page) => $page->component('Admin/Settings/Payments')->where('paystack.mode', 'test')->where('paystack.profiles.live.secret_hint', '••••ssss')->missing('paystack.secret_key')->missing('paystack.credentials'));
        $this->put('/admin/settings/payments', $this->data(['version' => 1, 'paystack_public_key' => '', 'paystack_secret_key' => '']))->assertSessionHasNoErrors();
        $this->assertSame('live', app(PaystackSettings::class)->mode());
        $this->assertSame('sk_test_'.str_repeat('t', 40), app(PaystackSettings::class)->secret('test'));
        $this->get('/admin/settings/payments')->assertInertia(fn (Assert $page) => $page->where('paymentMode', 'live')->where('paystack.version', 2));
        Http::assertSent(fn ($request) => $request->method() === 'GET' && $request->url() === 'https://api.paystack.co/balance');
        Http::assertSentCount(2);
    }

    public function test_failed_verification_and_stale_updates_leave_settings_unchanged_without_flashing_keys(): void
    {
        $this->environment(); $this->actingAs($this->admin());
        Http::fake(['https://api.paystack.co/balance' => Http::sequence()->push(['status' => false], 401)->push(['status' => true])->push(['status' => true])]);
        $this->from('/admin/settings/payments')->put('/admin/settings/payments', $this->data())->assertSessionHasErrors('paystack_secret_key')
            ->assertSessionMissing('_old_input.paystack_secret_key')->assertSessionMissing('_old_input.paystack_public_key')->assertSessionMissing('_old_input.current_password');
        $this->assertSame('test', app(PaystackSettings::class)->mode());
        $this->put('/admin/settings/payments', $this->data())->assertSessionHasNoErrors();
        $this->putJson('/admin/settings/payments', $this->data(['mode' => 'test', 'paystack_secret_key' => '', 'paystack_public_key' => '']))->assertUnprocessable()->assertJsonValidationErrors('version');
        $this->assertSame('live', app(PaystackSettings::class)->mode());
        $this->assertSame(1, PaymentGatewaySetting::first()->version);
    }

    public function test_existing_services_pick_up_switches_and_keep_historical_environment_for_verification(): void
    {
        $this->environment();
        $service = app(PaystackService::class);
        Http::fake(['https://api.paystack.co/*' => Http::response(['status' => true, 'data' => ['status' => 'success']])]);
        $service->verifyTransaction('before');
        $this->actingAs($this->admin())->put('/admin/settings/payments', $this->data())->assertSessionHasNoErrors();
        $service->verifyTransaction('new');
        $service->verifyTransaction('historical', 'test');
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/new') && $r->hasHeader('Authorization', 'Bearer '.$this->data()['paystack_secret_key']));
        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/historical') && $r->hasHeader('Authorization', 'Bearer sk_test_'.str_repeat('t', 40)));
        $settings = app(PaystackSettings::class);
        $payload = '{"event":"charge.success","data":{"reference":"old"}}';
        $this->assertSame('test', $settings->signedMode($payload, hash_hmac('sha512', $payload, $settings->secret('test'))));
        $this->assertSame('live', $settings->signedMode($payload, hash_hmac('sha512', $payload, $settings->secret('live'))));
        $this->assertNull($settings->signedMode($payload, 'fake'));
    }

    public function test_test_signed_webhook_cannot_touch_live_payment_and_old_checkouts_cannot_resume(): void
    {
        $this->environment(); $admin = $this->admin();
        Http::fake(['https://api.paystack.co/balance' => Http::response(['status' => true])]);
        $this->actingAs($admin)->put('/admin/settings/payments', $this->data())->assertSessionHasNoErrors();
        $order = Order::create(['user_id' => $admin->id, 'uuid' => 'live-order', 'status' => 'pending', 'subtotal' => 100, 'total' => 100, 'currency' => 'NGN']);
        $payment = Payment::create(['order_id' => $order->id, 'user_id' => $admin->id, 'gateway' => 'paystack', 'gateway_reference' => 'LIVE-ONLY', 'payment_mode' => 'live', 'amount' => 100, 'currency' => 'NGN', 'status' => 'pending']);
        $payload = ['event' => 'charge.success', 'data' => ['reference' => $payment->gateway_reference]];
        $raw = json_encode($payload);
        $this->call('POST', '/webhooks/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $raw, app(PaystackSettings::class)->secret('test'))], $raw)->assertOk();
        Http::assertSentCount(1);
        $this->assertSame('pending', $payment->fresh()->status);
        $payment->update(['payment_mode' => 'test']);
        $this->postJson('/orders/live-order/resume')->assertStatus(409);
        Http::assertSentCount(1);
    }
}
