<?php

namespace Tests\Feature;

use App\Mail\OrganizationInvitationMail;
use App\Models\Course;
use App\Models\Department;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OrganizationWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private function organization(int $seats = 3): array
    {
        $org = Organization::create(['name' => 'Company '.bin2hex(random_bytes(3)), 'contact_email' => 'admin@example.test', 'max_seats' => $seats]);
        $admin = User::factory()->create(['organization_id' => $org->id]);
        $admin->assignRole(Role::findOrCreate('org_admin', 'web'));
        Mail::fake();

        return [$org, $admin];
    }

    private function invitation(User $admin, string $email = 'invitee@example.test', ?int $department = null): string
    {
        $this->actingAs($admin)->post('/organization/invitations', ['email' => $email, 'department_id' => $department])->assertRedirect()->assertSessionHasNoErrors();
        $mail = Mail::queued(OrganizationInvitationMail::class)->last();
        $this->assertTrue((new SendQueuedMailable($mail))->shouldBeEncrypted);

        return $mail->acceptUrl;
    }

    public function test_invitation_reserves_a_seat_and_accepts_once_without_shared_passwords(): void
    {
        [$org, $admin] = $this->organization(2);
        $department = $org->departments()->create(['name' => 'Delivery']);
        $url = $this->invitation($admin, department: $department->id);
        $token = basename($url);
        $this->assertDatabaseHas('organization_invitations', ['token_hash' => hash('sha256', $token), 'status' => 'pending']);
        $this->assertSame(0, $org->seatUsage()['available']);
        $this->post('/organization/invitations', ['email' => 'second@example.test'])->assertSessionHasErrors('organization_id');
        Auth::logout();
        $this->get($url)->assertOk()->assertHeader('Referrer-Policy', 'no-referrer');
        $this->post($url, ['first_name' => 'New', 'last_name' => 'Learner', 'password' => 'IndividualPass123', 'password_confirmation' => 'IndividualPass123'])
            ->assertRedirect('/dashboard')->assertSessionHasNoErrors();
        $joined = User::where('email', 'invitee@example.test')->firstOrFail();
        $this->assertSame($org->id, $joined->organization_id);
        $this->assertTrue($joined->hasVerifiedEmail());
        $this->assertTrue($joined->hasRole('learner'));
        $this->assertDatabaseHas('department_user', ['department_id' => $department->id, 'user_id' => $joined->id]);
        $this->assertSame(2, $org->seatUsage()['used']);
        $this->assertSame(0, $org->seatUsage()['reserved']);
        $this->post($url)->assertNotFound();
        Mail::assertQueued(OrganizationInvitationMail::class, 1);
    }

    public function test_existing_accounts_require_the_matching_signed_in_user_and_keep_their_password(): void
    {
        [$org, $admin] = $this->organization();
        $learner = User::factory()->create(['email' => 'existing@example.test']);
        $password = $learner->getRawOriginal('password');
        $url = $this->invitation($admin, $learner->email);
        $this->post($url)->assertForbidden();
        Auth::logout();
        $this->post($url, ['password' => 'TakeoverPassword123'])->assertForbidden();
        $this->actingAs($learner)->post($url)->assertRedirect('/dashboard');
        $this->assertSame($password, $learner->fresh()->getRawOriginal('password'));
        $this->assertSame($org->id, $learner->fresh()->organization_id);
    }

    public function test_expired_revoked_and_cross_tenant_invitations_do_not_grant_membership(): void
    {
        [$org, $admin] = $this->organization(2);
        [$other, $otherAdmin] = $this->organization();
        $url = $this->invitation($admin);
        $invitation = OrganizationInvitation::firstOrFail();
        $this->actingAs($otherAdmin)->delete('/organization/invitations/'.$invitation->id)->assertNotFound();
        $this->actingAs($admin)->delete('/organization/invitations/'.$invitation->id)->assertRedirect();
        $this->get($url)->assertNotFound();
        $url = $this->invitation($admin, 'expired@example.test');
        $this->travel(8)->days();
        $this->get($url)->assertNotFound();
        $this->assertSame(1, $org->seatUsage()['available']);
        $this->post('/organization/invitations', ['email' => $otherAdmin->email])->assertSessionHasErrors('email');
    }

    public function test_csv_import_is_bounded_and_atomic_and_seat_guard_covers_direct_user_creation(): void
    {
        [$org, $admin] = $this->organization(3);
        $this->actingAs($admin)->post('/organization/people/import', ['file' => UploadedFile::fake()->createWithContent('people.csv', "email,first_name,last_name\nvalid@example.test,First,User\ninvalid,Second,User\n")])->assertSessionHasErrors('file');
        $this->assertDatabaseCount('organization_invitations', 0);
        $this->post('/organization/people/import', ['file' => UploadedFile::fake()->createWithContent('people.csv', "email\none@example.test\ntwo@example.test\n")])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('organization_invitations', 2);
        try {
            User::factory()->create(['organization_id' => $org->id]);
            $this->fail('Reserved seats must apply to all User creation paths.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('organization_id', $error->errors());
        }
        $this->assertSame(1, $org->users()->count());
        Mail::assertQueued(OrganizationInvitationMail::class, 2);
    }

    public function test_member_and_department_mutations_are_tenant_scoped_and_deactivation_revokes_access(): void
    {
        [$org, $admin] = $this->organization();
        [$other, $otherAdmin] = $this->organization();
        $member = User::factory()->create(['organization_id' => $org->id]);
        $department = Department::create(['organization_id' => $other->id, 'name' => 'Private']);
        $this->actingAs($admin)->patch('/organization/people/'.$otherAdmin->id, ['is_active' => false, 'department_ids' => []])->assertNotFound();
        $this->patch('/organization/people/'.$member->id, ['is_active' => true, 'department_ids' => [$department->id]])->assertSessionHasErrors('department_ids.0');
        $this->post('/organization/departments', ['name' => 'Delivery', 'manager_id' => $otherAdmin->id])->assertSessionHasErrors('manager_id');
        $this->post('/organization/departments', ['name' => 'Delivery', 'manager_id' => $member->id])->assertSessionHasNoErrors();
        $ownDepartment = $org->departments()->firstOrFail();
        $this->patch('/organization/people/'.$member->id, ['is_active' => true, 'department_ids' => [$ownDepartment->id]])->assertSessionHasNoErrors();
        $this->delete('/organization/departments/'.$ownDepartment->id)->assertSessionHasErrors('department');
        $member->createToken('synthetic-device');
        DB::table('sessions')->insert(['id' => 'synthetic-session', 'user_id' => $member->id, 'payload' => 'synthetic', 'last_activity' => time()]);
        $this->patch('/organization/people/'.$member->id, ['is_active' => false, 'department_ids' => []])->assertSessionHasNoErrors();
        $this->assertNull($ownDepartment->fresh()->manager_id);
        $this->assertSame(0, $member->tokens()->count());
        $this->assertDatabaseMissing('sessions', ['user_id' => $member->id]);
        $this->assertFalse($member->fresh()->is_active);
        $this->actingAs($member->fresh())->get('/dashboard')->assertForbidden();
    }

    public function test_learning_export_excludes_other_tenants_and_neutralizes_spreadsheet_formulas(): void
    {
        [$org, $admin] = $this->organization();
        [$other, $otherAdmin] = $this->organization();
        $course = Course::factory()->create(['title' => '=SUM(1,2)']);
        $course->enrollments()->create(['user_id' => $admin->id, 'status' => 'active', 'enrolled_at' => now()]);
        $course->enrollments()->create(['user_id' => $otherAdmin->id, 'status' => 'completed', 'enrolled_at' => now()]);
        $response = $this->actingAs($admin)->get('/organization/reports/export')->assertOk();
        $csv = $response->streamedContent();
        $this->assertStringContainsString($admin->email, $csv);
        $this->assertStringNotContainsString($otherAdmin->email, $csv);
        $this->assertStringContainsString("'=SUM(1,2)", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'learning_export', 'organization_id' => $org->id]);
    }

    public function test_platform_import_validates_every_row_and_never_installs_a_shared_default_password(): void
    {
        [$org, $admin] = $this->organization(2);
        $platform = User::factory()->create();
        $platform->assignRole(Role::findOrCreate('platform_admin', 'web'));
        Role::findOrCreate('learner', 'web');
        $this->actingAs($platform);
        $payload = ['organization_id' => $org->id, 'role' => 'learner'];
        $file = fn ($csv) => UploadedFile::fake()->createWithContent('people.csv', $csv);
        $this->post('/admin/users/import', [...$payload, 'csv_file' => $file("first_name,last_name,email\nFirst,Learner,one@example.test\nSecond\n")])->assertSessionHasErrors('csv_file');
        $this->assertDatabaseMissing('users', ['email' => 'one@example.test']);
        $this->post('/admin/users/import', [...$payload, 'csv_file' => $file("first_name,last_name,email\nFirst,Learner,one@example.test\nSecond,Learner,two@example.test\n")])->assertSessionHasErrors('organization_id');
        $this->assertDatabaseMissing('users', ['email' => 'one@example.test']);
        $this->post('/admin/users/import', [...$payload, 'csv_file' => $file("first_name,last_name,email\nFirst,Learner,one@example.test\n")])->assertSessionHasNoErrors();
        $imported = User::where('email', 'one@example.test')->firstOrFail();
        $this->assertTrue($imported->hasRole('learner'));
        $this->assertFalse(Hash::check('Changeme1!', $imported->password));
        $this->assertFalse($imported->hasVerifiedEmail());
        Mail::assertNothingOutgoing();
    }

    public function test_organization_pages_and_legacy_department_memberships_do_not_expose_another_tenant(): void
    {
        [$org, $admin] = $this->organization();
        [$other] = $this->organization();
        $private = $other->departments()->create(['name' => 'Confidential tenant department']);
        $admin->departments()->attach($private);
        $this->actingAs($admin)->get('/organization/people')->assertOk()->assertInertia(fn ($page) => $page->component('Organization/People')->has('people.data', 1)->has('people.data.0.departments', 0));
        $this->get('/organization/departments')->assertOk();
        $this->get('/organization/reports')->assertOk();
        $this->patch('/organization/departments/'.$private->id, ['name' => 'Foreign edit'])->assertNotFound();
        $this->delete('/organization/departments/'.$private->id)->assertNotFound();
    }
}
