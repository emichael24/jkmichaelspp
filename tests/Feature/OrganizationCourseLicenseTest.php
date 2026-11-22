<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseEnrollment;
use App\Models\Department;
use App\Models\Order;
use App\Models\Organization;
use App\Models\OrganizationCourseAssignment;
use App\Models\OrganizationCourseLicense;
use App\Models\Product;
use App\Models\User;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationCourseLicenseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.paystack.secret_key' => 'sk_test_fixture']);
        Http::preventStrayRequests();
        Mail::fake();
    }

    private function company(): array
    {
        $org = Organization::create(['name' => 'Company '.bin2hex(random_bytes(3)), 'contact_email' => 'billing@example.test', 'address' => '12 Learning Way', 'country' => 'NGA', 'max_seats' => 100]);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole(Role::findOrCreate('org_admin', 'web'));

        return [$org, $admin];
    }

    private function learner(Organization $org): User
    {
        $user = User::factory()->create(['organization_id' => $org->id]);
        $user->assignRole(Role::findOrCreate('learner', 'web'));

        return $user;
    }

    private function license(Organization $org, int $quantity = 2, ?Course $course = null): OrganizationCourseLicense
    {
        return OrganizationCourseLicense::create(['organization_id' => $org->id, 'course_id' => ($course ?? Course::factory()->create())->id, 'quantity' => $quantity]);
    }

    private function assign(User $admin, OrganizationCourseLicense $license, array $ids, array $extra = [])
    {
        return $this->actingAs($admin)->post('/organization/licenses/'.$license->id.'/assign', ['user_ids' => $ids, ...$extra]);
    }

    private function purchase(User $admin, Course $course, int $quantity = 3): Order
    {
        Http::fake(['*/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/synthetic']])]);
        $this->actingAs($admin)->post('/organization/courses/'.$course->id.'/purchase', ['quantity' => $quantity, 'payment_gateway' => 'paystack'])->assertRedirect('https://checkout.paystack.com/synthetic');

        return Order::latest('id')->firstOrFail();
    }

    private function settle(Order $order): void
    {
        $payment = $order->payments()->first();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::preventStrayRequests();
        Http::fake(['*/transaction/verify/*' => Http::response(['data' => ['status' => 'success', 'reference' => $payment->gateway_reference, 'amount' => (int) round($payment->amount * 100), 'currency' => $payment->currency]])]);
        $this->assertTrue(app(PaymentService::class)->verifyPaystackWebhook(['data' => ['reference' => $payment->gateway_reference]]));
    }

    public function test_platform_allocates_courses_but_company_admins_cannot_grant_or_change_license_capacity(): void
    {
        [$org, $admin] = $this->company();
        $platform = User::factory()->create();
        $platform->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $course = Course::factory()->create();
        $payload = ['course_id' => $course->id, 'quantity' => 10, 'reason' => 'Training agreement QA-123', 'expires_at' => today()->addMonth()->toDateString()];
        $this->actingAs($admin)->postJson('/admin/organizations/'.$org->id.'/licenses', $payload)->assertForbidden();
        $this->actingAs($platform)->post('/admin/organizations/'.$org->id.'/licenses', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $license = OrganizationCourseLicense::firstOrFail();
        $this->assertSame(10, $license->quantity);
        $this->assertSame($platform->id, $license->granted_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_license_allocated']);
        $this->get('/admin/organizations/'.$org->id.'/licenses')->assertOk();
        $draft = Course::factory()->create(['is_published' => false]);
        $this->post('/admin/organizations/'.$org->id.'/licenses', [...$payload, 'course_id' => $draft->id])->assertNotFound();
        Mail::assertNothingSent();
    }

    public function test_company_can_assign_members_once_without_consuming_extra_membership_seats_or_resetting_progress(): void
    {
        [$org, $admin] = $this->company();
        $user = $this->learner($org);
        $license = $this->license($org);
        $this->assign($admin, $license, [$user->id], ['deadline' => today()->addDays(14)->toDateString()])->assertSessionHasNoErrors();
        $enrollment = CourseEnrollment::firstOrFail();
        $this->assertTrue($enrollment->allowsAccess());
        $enrollment->update(['progress_pct' => 45]);
        $this->assign($admin, $license, [$user->id], ['deadline' => today()->addDays(30)->toDateString()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('course_enrollments', 1);
        $this->assertDatabaseCount('organization_course_assignments', 1);
        $this->assertSame(45, $enrollment->fresh()->progress_pct);
        $this->assertSame(today()->addDays(14)->toDateString(), $enrollment->fresh()->deadline->toDateString());
        $this->assertSame(2, $org->seatUsage()['used']);
        $this->get('/organization/licenses/'.$license->id)->assertInertia(fn (Assert $page) => $page->component('Organization/Courses/Show')->where('license.used', 1)->has('assignments.data', 1));
    }

    public function test_department_assignment_is_atomic_and_uses_current_eligible_members_only(): void
    {
        [$org, $admin] = $this->company();
        [$other] = $this->company();
        $a = $this->learner($org);
        $b = $this->learner($org);
        $foreign = $this->learner($other);
        $department = Department::create(['organization_id' => $org->id, 'name' => 'Operations']);
        $department->users()->attach([$a->id, $b->id, $foreign->id]);
        $license = $this->license($org, 1);
        $this->assign($admin, $license, [], ['department_id' => $department->id])->assertSessionHasErrors('members');
        $this->assertDatabaseCount('course_enrollments', 0);
        $this->assertDatabaseCount('organization_course_assignments', 0);
        $license->update(['quantity' => 2]);
        $this->assign($admin, $license, [], ['department_id' => $department->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('course_enrollments', 2);
        $this->assertDatabaseMissing('course_enrollments', ['user_id' => $foreign->id]);
        $late = $this->learner($org);
        $department->users()->attach($late->id);
        $this->assertDatabaseMissing('course_enrollments', ['user_id' => $late->id]);
    }

    public function test_foreign_members_licenses_departments_and_inactive_actors_are_rejected(): void
    {
        [$org, $admin] = $this->company();
        [$other, $otherAdmin] = $this->company();
        $member = $this->learner($org);
        $foreign = $this->learner($other);
        $license = $this->license($org);
        $this->assign($admin, $license, [$foreign->id])->assertSessionHasErrors('members');
        $this->actingAs($otherAdmin)->get('/organization/licenses/'.$license->id)->assertNotFound();
        $this->get('/organization/licenses/'.$license->id.'/members?search=test')->assertNotFound();
        $this->assign($otherAdmin, $license, [$foreign->id])->assertNotFound();
        $department = Department::create(['organization_id' => $other->id, 'name' => 'Foreign']);
        $this->assign($admin, $license, [], ['department_id' => $department->id])->assertNotFound();
        $this->actingAs($member)->getJson('/organization/courses')->assertForbidden();
        $admin->update(['is_active' => false]);
        $this->assign($admin->fresh(), $license, [$member->id])->assertForbidden();
        $this->assertDatabaseCount('course_enrollments', 0);
    }

    public function test_release_expiry_revocation_and_membership_changes_remove_only_license_access(): void
    {
        [$org, $admin] = $this->company();
        $user = $this->learner($org);
        $license = $this->license($org);
        $this->assign($admin, $license, [$user->id])->assertSessionHasNoErrors();
        $enrollment = CourseEnrollment::firstOrFail();
        $enrollment->update(['status' => 'completed', 'progress_pct' => 100, 'completed_at' => now()]);
        $assignment = OrganizationCourseAssignment::firstOrFail();
        $license->update(['expires_at' => now()->subMinute()]);
        $this->assertFalse($enrollment->fresh()->allowsAccess());
        $license->update(['expires_at' => null, 'status' => 'revoked']);
        $this->assertFalse($enrollment->fresh()->allowsAccess());
        $license->update(['status' => 'active']);
        $this->assertTrue($enrollment->fresh()->allowsAccess());
        $user->update(['organization_id' => null]);
        $this->assertFalse($enrollment->fresh()->allowsAccess());
        $user->update(['organization_id' => $org->id]);
        $this->actingAs($admin)->delete('/organization/licenses/'.$license->id.'/assignments/'.$assignment->id)->assertSessionHasNoErrors();
        $this->assertFalse($enrollment->fresh()->allowsAccess());
        $this->assertSame(100, $enrollment->fresh()->progress_pct);
        $this->assertNotNull($enrollment->fresh()->completed_at);
        $this->assign($admin, $license, [$user->id])->assertSessionHasNoErrors();
        $this->assertTrue($enrollment->fresh()->allowsAccess());
        $this->assertSame('completed', $enrollment->fresh()->status);
    }

    public function test_existing_independent_access_is_preserved_and_suspensions_and_prerequisites_block_atomic_assignment(): void
    {
        [$org, $admin] = $this->company();
        $a = $this->learner($org);
        $b = $this->learner($org);
        $license = $this->license($org);
        $existing = CourseEnrollment::create(['user_id' => $a->id, 'course_id' => $license->course_id, 'status' => 'active', 'enrolled_at' => now()]);
        $this->assign($admin, $license, [$a->id])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('organization_course_assignments', 0);
        $this->assertNull($existing->fresh()->organization_course_license_id);
        $existing->update(['status' => 'suspended']);
        $this->assign($admin, $license, [$b->id, $a->id])->assertSessionHasErrors('members');
        $this->assertDatabaseCount('course_enrollments', 1);
        $required = Course::factory()->create();
        DB::table('course_prerequisites')->insert(['course_id' => $license->course_id, 'prerequisite_id' => $required->id]);
        $this->assign($admin, $license, [$b->id])->assertSessionHasErrors('members');
        $this->assertDatabaseCount('course_enrollments', 1);
    }

    public function test_verified_company_purchase_grants_bulk_license_once_and_never_enrolls_purchaser(): void
    {
        [$org, $admin] = $this->company();
        $course = Course::factory()->create(['price' => 200, 'discount_price' => null, 'currency' => 'NGN']);
        $order = $this->purchase($admin, $course, 5);
        $this->assertSame('organization', $order->purchase_scope);
        $this->assertEquals(1000, $order->total);
        $this->assertSame($org->name, $order->billing_details['name']);
        $this->assertSame('test', $order->billing_details['payment_mode']);
        $this->assertDatabaseCount('organization_course_licenses', 0);
        $duplicate = $this->purchase($admin, $course, 5);
        $this->assertSame($order->id, $duplicate->id);
        $this->settle($order);
        app(PaymentService::class)->fulfillOrder($order->fresh());
        $this->assertDatabaseCount('organization_course_licenses', 1);
        $this->assertDatabaseCount('course_enrollments', 0);
        $license = OrganizationCourseLicense::firstOrFail();
        $this->assertSame(5, $license->quantity);
        $member = $this->learner($org);
        $this->assign($admin, $license, [$member->id])->assertSessionHasNoErrors();
        $this->assertTrue(CourseEnrollment::first()->allowsAccess());
        $order->update(['status' => 'refunded']);
        $this->assertFalse(CourseEnrollment::first()->allowsAccess());
        $this->assign($admin, $license, [$member->id])->assertSessionHasErrors('license');
    }

    public function test_company_billing_is_scoped_to_current_admins_and_individual_orders_stay_private(): void
    {
        [$org, $admin] = $this->company();
        [$other, $otherAdmin] = $this->company();
        $course = Course::factory()->create(['price' => 100, 'discount_price' => null]);
        $order = $this->purchase($admin, $course);
        $colleague = $this->learner($org);
        $colleague->assignRole(Role::findOrCreate('org_admin', 'web'));
        $this->actingAs($colleague)->get('/organization/billing/'.$order->uuid)->assertOk();
        $this->get('/orders/'.$order->uuid)->assertRedirect('/organization/billing/'.$order->uuid);
        $this->post('/orders/'.$order->uuid.'/resume')->assertRedirect('https://checkout.paystack.com/synthetic');
        $this->actingAs($otherAdmin)->get('/organization/billing/'.$order->uuid)->assertNotFound();
        $this->post('/orders/'.$order->uuid.'/resume')->assertNotFound();
        $this->post('/orders/'.$order->uuid.'/refresh')->assertNotFound();
        $admin->update(['organization_id' => $other->id]);
        $this->actingAs($admin->fresh())->get('/orders/'.$order->uuid)->assertNotFound();
        $individual = Order::create(['user_id' => $colleague->id, 'organization_id' => $org->id, 'subtotal' => 0, 'total' => 0, 'currency' => 'NGN', 'status' => 'paid']);
        $this->actingAs($colleague)->get('/organization/billing/'.$individual->uuid)->assertNotFound();
        $this->get('/organization/billing')->assertInertia(fn (Assert $page) => $page->has('orders.data', 1));
    }

    public function test_paid_course_button_prepares_real_checkout_and_never_grants_unpaid_access(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create(['price' => 5000, 'discount_price' => null]);
        $this->actingAs($user)->post('/courses/'.$course->id.'/enroll')->assertRedirect('/storefront/course-'.$course->uuid);
        $this->post('/courses/'.$course->id.'/enroll')->assertRedirect('/storefront/course-'.$course->uuid);
        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('course_enrollments', 0);
        $this->assertDatabaseCount('orders', 0);
        $product = Product::firstOrFail();
        $this->assertEquals(5000, $product->price);
        $this->get('/storefront/'.$product->slug)->assertOk();
        $product->update(['is_active' => false]);
        $this->post('/courses/'.$course->id.'/enroll')->assertSessionHasErrors('checkout');
        $this->assertFalse($product->fresh()->is_active);
    }

    public function test_personal_purchase_replaces_license_dependency_without_losing_progress(): void
    {
        [$org, $admin] = $this->company();
        $member = $this->learner($org);
        $license = $this->license($org);
        $this->assign($admin, $license, [$member->id])->assertSessionHasNoErrors();
        $enrollment = CourseEnrollment::firstOrFail();
        $enrollment->update(['progress_pct' => 30]);
        $product = Product::create(['productable_type' => Course::class, 'productable_id' => $license->course_id, 'name' => 'Independent course', 'slug' => 'independent', 'price' => 0, 'currency' => 'NGN']);
        app(PaymentService::class)->createOrder($member, [['product_id' => $product->id]]);
        $license->update(['status' => 'revoked']);
        $this->assertTrue($enrollment->fresh()->allowsAccess());
        $this->assertNull($enrollment->fresh()->organization_course_license_id);
        $this->assertSame(30, $enrollment->fresh()->progress_pct);
    }

    public function test_capacity_cannot_be_reduced_below_usage_and_released_places_can_be_reassigned(): void
    {
        [$org, $admin] = $this->company();
        $a = $this->learner($org);
        $b = $this->learner($org);
        $c = $this->learner($org);
        $license = $this->license($org, 2);
        $this->assign($admin, $license, [$a->id, $b->id])->assertSessionHasNoErrors();
        $this->assign($admin, $license, [$c->id])->assertSessionHasErrors('members');
        $platform = User::factory()->create();
        $platform->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($platform)->patch('/admin/organizations/'.$org->id.'/licenses/'.$license->id, ['quantity' => 1, 'status' => 'active', 'reason' => 'Reduce company allocation'])->assertSessionHasErrors('quantity');
        $assignment = OrganizationCourseAssignment::where('user_id', $a->id)->first();
        $this->actingAs($admin)->delete('/organization/licenses/'.$license->id.'/assignments/'.$assignment->id)->assertSessionHasNoErrors();
        $this->assign($admin, $license, [$c->id])->assertSessionHasNoErrors();
        $this->assertSame(2, $license->assignments()->whereNull('released_at')->count());
        $this->assertDatabaseCount('course_enrollments', 3);
    }

    public function test_paid_license_terms_are_immutable_and_payment_mismatch_does_not_allocate_places(): void
    {
        [$org, $admin] = $this->company();
        $course = Course::factory()->create(['price' => 250, 'discount_price' => null]);
        $order = $this->purchase($admin, $course, 4);
        $payment = $order->payments()->first();
        Http::fake(['*/transaction/verify/*' => Http::response(['data' => ['status' => 'success', 'reference' => $payment->gateway_reference, 'amount' => 1, 'currency' => $payment->currency]])]);
        $this->assertFalse(app(PaymentService::class)->refreshOrder($order, $admin));
        $this->assertDatabaseCount('organization_course_licenses', 0);
        $this->settle($order);
        $license = OrganizationCourseLicense::firstOrFail();
        $platform = User::factory()->create();
        $platform->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $path = '/admin/organizations/'.$org->id.'/licenses/'.$license->id;
        $this->actingAs($platform)->patch($path, ['quantity' => 20, 'status' => 'active', 'reason' => 'Attempt changed purchase'])->assertSessionHasErrors('quantity');
        $this->patch($path, ['quantity' => 4, 'status' => 'revoked', 'reason' => 'Manual access review'])->assertSessionHasNoErrors();
        $this->assertFalse($license->fresh()->availableForAssignment());
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_zero_value_company_order_and_purchase_scope_are_explicit(): void
    {
        [$org, $admin] = $this->company();
        $course = Course::factory()->create(['price' => 0, 'discount_price' => null]);
        $this->actingAs($admin)->post('/organization/courses/'.$course->id.'/purchase', ['quantity' => 8, 'payment_gateway' => 'paystack'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(8, OrganizationCourseLicense::firstOrFail()->quantity);
        $this->assertDatabaseCount('course_enrollments', 0);
        $this->assertSame('paid', Order::firstOrFail()->status);
        $this->post('/orders', ['items' => [['product_id' => Product::first()->id, 'quantity' => 8]], 'payment_gateway' => 'paystack', 'organization_id' => $org->id, 'purchase_scope' => 'organization'])->assertSessionHasErrors('items.0.quantity');
        $this->post('/organization/courses/'.$course->id.'/purchase', ['quantity' => 10001, 'payment_gateway' => 'paystack'])->assertSessionHasErrors('quantity');
        Http::assertNothingSent();
    }
}
