<?php

namespace Tests\Feature;

use App\Models\Bundle;
use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\DiscountCode;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CheckoutRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_fixture']);
    }

    private function product(int $price = 100): Product
    {
        $course = Course::factory()->create();

        return Product::create(['productable_type' => Course::class, 'productable_id' => $course->id, 'name' => $course->title, 'slug' => $course->slug, 'price' => $price, 'currency' => 'NGN', 'is_active' => true]);
    }

    private function checkout(User $user, Product $product, ?string $code = null): array
    {
        return app(PaymentService::class)->createOrder($user, [['product_id' => $product->id]], $code);
    }

    public function test_initialization_failure_preserves_order_and_reference_and_resume_reuses_checkout(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        Http::fake(['*/transaction/initialize' => Http::sequence()->push(['status' => false], 503)->push(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/synthetic']])]);
        $result = $this->checkout($user, $product);
        $this->assertNotEmpty($result['checkout']['error']);
        $reference = $result['payment']->gateway_reference;
        $repeated = $this->checkout($user, $product);
        $this->assertSame($result['order']->id, $repeated['order']->id);
        $this->actingAs($user)->withHeaders(['X-Inertia' => 'true'])->post('/orders/'.$result['order']->uuid.'/resume')->assertStatus(409)->assertHeader('X-Inertia-Location', 'https://checkout.paystack.com/synthetic');
        app(PaymentService::class)->resumeCheckout($result['order'], $user);
        $this->actingAs($user)->postJson('/payments/paystack/initialize', ['order_id' => $result['order']->id, 'amount' => 1])->assertOk()->assertJsonPath('reference', $reference);
        $this->assertDatabaseCount('paystack_transactions', 0);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['reference'] === $reference);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame('pending', $result['order']->fresh()->status);
    }

    public function test_status_refresh_preserves_uncertain_outcome_then_verifies_exact_settlement_once(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        Http::fake(['*/transaction/initialize' => Http::response([], 503)]);
        $result = $this->checkout($user, $product);
        $reference = $result['payment']->gateway_reference;
        $data = ['status' => 'success', 'reference' => $reference, 'amount' => 10000, 'currency' => 'NGN', 'authorization' => ['authorization_code' => 'must-not-persist'], 'customer' => ['email' => $user->email]];
        Http::fake(['*/transaction/verify/*' => Http::sequence()->push([], 503)->push(['data' => array_replace($data, ['amount' => 1])])->push(['data' => $data])]);
        $service = app(PaymentService::class);
        $this->assertFalse($service->refreshOrder($result['order'], $user));
        $this->assertFalse($service->refreshOrder($result['order'], $user));
        $this->assertSame('pending', $result['payment']->fresh()->status);
        $this->assertDatabaseCount('course_enrollments', 0);
        $this->assertTrue($service->refreshOrder($result['order'], $user));
        $this->assertTrue($service->refreshOrder($result['order'], $user));
        $this->assertDatabaseCount('course_enrollments', 1);
        $this->assertSame($product->productable_id, CourseEnrollment::first()->course_id);
        $this->assertArrayNotHasKey('authorization', $result['payment']->fresh()->gateway_response);
        $this->assertArrayNotHasKey('customer', $result['payment']->fresh()->gateway_response);
    }

    public function test_product_coupon_discounts_only_eligible_lines_and_cannot_be_used_after_capacity(): void
    {
        $user = User::factory()->create();
        $eligible = $this->product(100);
        $other = $this->product(300);
        $discount = DiscountCode::create(['code' => 'ONE-USE', 'type' => 'percentage', 'value' => 50, 'product_id' => $eligible->id, 'max_uses' => 1, 'is_active' => true]);
        Http::fake(['*/transaction/initialize' => Http::response([], 503)]);
        $result = app(PaymentService::class)->createOrder($user, [['product_id' => $eligible->id], ['product_id' => $other->id]], $discount->code);
        $this->assertSame('50.00', $result['order']->discount_amount);
        $this->assertSame('350.00', $result['order']->total);
        $this->assertSame(1, $discount->fresh()->used_count);
        $this->actingAs(User::factory()->create())->postJson('/orders', ['items' => [['product_id' => $eligible->id]], 'payment_gateway' => 'paystack', 'discount_code' => $discount->code])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 1);
        $this->assertFalse($discount->fresh()->isValid());
    }

    public function test_invalid_coupon_and_multiple_seat_quantity_do_not_create_an_order(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        Http::fake();
        $this->actingAs($user)->postJson('/orders', ['items' => [['product_id' => $product->id]], 'payment_gateway' => 'paystack', 'discount_code' => 'DOES-NOT-EXIST'])->assertUnprocessable();
        $this->postJson('/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]], 'payment_gateway' => 'paystack'])->assertUnprocessable();
        $this->postJson('/orders', ['items' => [['product_id' => $product->id]], 'payment_gateway' => 'stripe'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_zero_value_order_fulfills_without_contacting_a_provider(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        DiscountCode::create(['code' => 'FREE', 'type' => 'fixed', 'value' => 200, 'is_active' => true]);
        Http::fake();
        $result = $this->checkout($user, $product, 'FREE');
        $this->assertTrue($result['checkout']['completed']);
        $this->assertSame('paid', $result['order']->fresh()->status);
        $this->assertSame('0.00', $result['payment']->amount);
        $this->assertDatabaseCount('course_enrollments', 1);
        Http::assertNothingSent();
    }

    public function test_another_owner_and_revoked_user_cannot_resume_or_verify_order(): void
    {
        Http::fake(['*/transaction/initialize' => Http::response([], 503)]);
        $user = User::factory()->create();
        $result = $this->checkout($user, $this->product());
        $this->actingAs(User::factory()->create())->postJson('/orders/'.$result['order']->uuid.'/resume')->assertNotFound();
        $this->postJson('/orders/'.$result['order']->uuid.'/refresh-payment')->assertNotFound();
        $user->update(['is_active' => false]);
        $this->actingAs($user)->postJson('/orders/'.$result['order']->uuid.'/resume')->assertForbidden();
        $this->assertSame('pending', $result['order']->fresh()->status);
        Http::assertSentCount(1);
    }

    public function test_storefront_hides_draft_bundles_and_exposes_only_display_fields_and_configured_gateways(): void
    {
        $user = User::factory()->create();
        $product = $this->product();
        $draft = $this->product();
        $draft->productable->update(['is_published' => false]);
        $bundle = Bundle::create(['name' => 'Hidden draft bundle', 'slug' => 'hidden-bundle']);
        $bundle->courses()->attach([$product->productable_id, $draft->productable_id]);
        $bundleProduct = Product::create(['name' => $bundle->name, 'slug' => $bundle->slug, 'productable_type' => Bundle::class, 'productable_id' => $bundle->id, 'price' => 300, 'currency' => 'NGN', 'is_active' => true]);
        $this->actingAs($user)->get('/storefront')->assertInertia(fn (Assert $page) => $page->has('products.data', 1)->has('bundles', 0));
        $this->get('/storefront/'.$draft->slug)->assertNotFound();
        $this->get('/storefront/'.$bundleProduct->slug)->assertNotFound();
        $this->get('/storefront/'.$product->slug)->assertInertia(fn (Assert $page) => $page->has('gateways', 1)->where('gateways.0.id', 'paystack')->where('paymentMode', 'test')->missing('product.productable.author_id')->missing('product.productable.ai_context'));
        Http::fake();
        $this->postJson('/orders', ['items' => [['product_id' => $bundleProduct->id]], 'payment_gateway' => 'paystack'])->assertUnprocessable();
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }
}
