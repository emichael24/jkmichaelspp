<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminOrganizationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('platform_admin', 'web'));
        Mail::fake();

        return $user;
    }

    private function company(array $fields = []): Organization
    {
        return Organization::create([...$this->data(), 'slug' => 'org-'.bin2hex(random_bytes(4)), ...$fields]);
    }

    private function data(array $fields = []): array
    {
        return ['name' => 'Acme Learning', 'contact_email' => 'office@acme.example', 'country' => 'NGA',
            'max_seats' => 10, 'is_active' => true, 'admin_mode' => 'none', ...$fields];
    }

    private function newAdmin(array $fields = []): array
    {
        return $this->data(['admin_mode' => 'new', 'admin_first_name' => 'Ada', 'admin_last_name' => 'Morgan',
            'admin_email' => 'ada@acme.example', 'admin_password' => 'SpecificPrivatePass42', 'admin_password_confirmation' => 'SpecificPrivatePass42', ...$fields]);
    }

    public function test_only_platform_administrators_can_manage_organizations_or_search_accounts(): void
    {
        $org = $this->company();
        $this->get('/admin/organizations')->assertRedirect('/login');
        foreach (['learner', 'org_admin', 'facilitator'] as $role) {
            $user = User::factory()->create(['organization_id' => $org->id]);
            $user->assignRole(Role::findOrCreate($role, 'web'));
            $this->actingAs($user)->getJson('/admin/organizations')->assertForbidden();
            $this->getJson('/admin/organizations/create')->assertForbidden();
            $this->getJson('/admin/organizations/'.$org->id.'/edit')->assertForbidden();
            $this->getJson('/admin/organizations/administrator-candidates?search=Ada')->assertForbidden();
            $this->postJson('/admin/organizations', $this->data())->assertForbidden();
            $this->putJson('/admin/organizations/'.$org->id, $this->data())->assertForbidden();
        }
    }

    public function test_create_with_a_new_admin_is_atomic_and_keeps_passwords_out_of_audit_and_flash(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', $this->newAdmin(['size' => '51-200', 'country' => 'nga']))->assertSessionHasNoErrors()->assertRedirect();
        $org = Organization::where('name', 'Acme Learning')->firstOrFail();
        $user = User::where('email', 'ada@acme.example')->firstOrFail();
        $this->assertSame($org->id, $user->organization_id);
        $this->assertTrue($user->hasRole('org_admin'));
        $this->assertFalse($user->hasVerifiedEmail());
        $this->assertTrue(Hash::check('SpecificPrivatePass42', $user->password));
        $this->assertSame('NGA', $org->country);
        $this->assertSame(1, $org->seatUsage()['used']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_created', 'auditable_id' => $org->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization_administrator_assigned', 'auditable_id' => $org->id]);
        $this->assertStringNotContainsString('SpecificPrivatePass42', AuditLog::all()->toJson());
        $this->post('/admin/organizations', $this->newAdmin(['admin_password_confirmation' => 'DoesNotMatch123']))->assertSessionHasErrors('admin_password');
        $this->assertNull(session()->getOldInput('admin_password'));
        $this->assertNull(session()->getOldInput('admin_password_confirmation'));
        $this->assertDatabaseCount('organizations', 1);
        Mail::assertNothingOutgoing();
    }

    public function test_existing_assignment_preserves_credentials_and_roles_and_does_not_cross_tenants(): void
    {
        $actor = $this->admin();
        $learner = User::factory()->create();
        $learner->assignRole(Role::findOrCreate('learner', 'web'));
        $password = $learner->password;
        $this->actingAs($actor)->post('/admin/organizations', $this->data(['admin_mode' => 'existing', 'admin_user_id' => $learner->id]))->assertSessionHasNoErrors();
        $org = Organization::firstOrFail();
        $this->assertSame($org->id, $learner->fresh()->organization_id);
        $this->assertSame($password, $learner->fresh()->password);
        $this->assertTrue($learner->fresh()->hasAllRoles(['learner', 'org_admin']));
        $this->post('/admin/organizations', $this->data(['name' => 'Other tenant', 'admin_mode' => 'existing', 'admin_user_id' => $learner->id]))->assertSessionHasErrors('admin_user_id');
        $this->assertDatabaseCount('organizations', 1);
        $this->assertSame($org->id, $learner->fresh()->organization_id);
        $this->post('/admin/organizations', $this->data(['admin_mode' => 'existing', 'admin_user_id' => $actor->id]))->assertSessionHasErrors('admin_user_id');
        $this->assertDatabaseCount('organizations', 1);
    }

    public function test_seat_reduction_and_new_admin_respect_active_members_and_reserved_invitations(): void
    {
        $actor = $this->admin();
        $org = $this->company(['max_seats' => 2]);
        $member = User::factory()->create(['organization_id' => $org->id]);
        OrganizationInvitation::create(['organization_id' => $org->id, 'invited_by' => $actor->id, 'email' => 'reserved@acme.example', 'token_hash' => hash('sha256', 'test'), 'expires_at' => now()->addDays(7)]);
        $this->actingAs($actor)->put('/admin/organizations/'.$org->id, $this->data(['max_seats' => 1]))->assertSessionHasErrors('max_seats');
        $this->assertSame(2, $org->fresh()->max_seats);
        $this->put('/admin/organizations/'.$org->id, $this->newAdmin(['name' => 'Must roll back', 'max_seats' => 2]))->assertSessionHasErrors('organization_id');
        $this->assertSame('Acme Learning', $org->fresh()->name);
        $this->assertDatabaseMissing('users', ['email' => 'ada@acme.example']);
        $this->put('/admin/organizations/'.$org->id, $this->data(['max_seats' => 2, 'admin_mode' => 'existing', 'admin_user_id' => $member->id]))->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->hasRole('org_admin'));
        $this->assertSame(2, $org->fresh()->seatUsage()['used'] + $org->fresh()->seatUsage()['reserved']);
    }

    public function test_inactive_organizations_revoke_member_access_and_can_be_reactivated_without_deleting_records(): void
    {
        $actor = $this->admin();
        $org = $this->company();
        $member = User::factory()->create(['organization_id' => $org->id]);
        $member->assignRole(Role::findOrCreate('org_admin', 'web'));
        $member->createToken('device');
        DB::table('sessions')->insert(['id' => 'member-device', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
        $this->actingAs($actor)->put('/admin/organizations/'.$org->id, $this->data(['is_active' => false]))->assertSessionHasNoErrors();
        $this->assertFalse($org->fresh()->is_active);
        $this->assertDatabaseMissing('sessions', ['id' => 'member-device']);
        $this->assertSame(0, $member->tokens()->count());
        $this->actingAs($member)->get('/organization')->assertForbidden();
        $this->actingAs($actor)->put('/admin/organizations/'.$org->id, $this->data(['is_active' => true]))->assertSessionHasNoErrors();
        $this->assertTrue($member->fresh()->is_active);
        $this->actingAs($member->fresh())->get('/organization')->assertOk();
    }

    public function test_directory_and_bounded_candidate_search_show_only_eligible_active_accounts(): void
    {
        $actor = $this->admin();
        $org = $this->company();
        $other = $this->company(['name' => 'Other organization', 'is_active' => false]);
        $eligible = User::factory()->create(['first_name' => 'Searchable', 'organization_id' => $org->id]);
        User::factory()->create(['first_name' => 'Searchable', 'is_active' => false]);
        // Create while active to obey the global membership guard, then deactivate.
        $other->update(['is_active' => true]);
        User::factory()->create(['first_name' => 'Searchable', 'organization_id' => $other->id]);
        $other->update(['is_active' => false]);
        $this->actingAs($actor)->get('/admin/organizations?search=Acme&status=active')->assertOk()->assertInertia(fn ($p) => $p->component('Admin/Organizations/Index')->has('organizations.data', 1)->where('organizations.data.0.active_members_count', 1));
        $this->get('/admin/organizations/create')->assertOk();
        $this->get('/admin/organizations/'.$org->id.'/edit')->assertOk();
        $response = $this->getJson('/admin/organizations/administrator-candidates?search=Searchable&organization_id='.$org->id)->assertOk()->assertJsonCount(1, 'users')->assertJsonPath('users.0.id', $eligible->id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->getJson('/admin/organizations/administrator-candidates?search=S')->assertUnprocessable();
    }

    public function test_invalid_company_fields_and_inactive_admin_setup_never_leave_partial_organizations(): void
    {
        $this->actingAs($this->admin())->post('/admin/organizations', $this->data(['size' => 'a very big company', 'country' => 'Nigeria', 'max_seats' => 0]))->assertSessionHasErrors(['size', 'country', 'max_seats']);
        $this->post('/admin/organizations', $this->newAdmin(['is_active' => false]))->assertSessionHasErrors('admin_mode');
        $this->assertDatabaseCount('organizations', 0);
        $this->assertDatabaseMissing('users', ['email' => 'ada@acme.example']);
        Mail::assertNothingOutgoing();
    }
}
