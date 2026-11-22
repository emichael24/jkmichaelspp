<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CrmContact;
use App\Models\User;
use App\Services\CrmContactService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CrmContactWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($user);
        Mail::fake();

        return $user;
    }

    private function contact(array $values = []): array
    {
        return $values + ['first_name' => 'Ada', 'last_name' => 'Okoro', 'email' => 'ada@example.test', 'type' => 'customer', 'status' => 'new', 'company' => 'Practice Ltd', 'job_title' => 'Project Lead', 'source' => 'Workshop', 'address_line_1' => '12 Learning Street', 'city' => 'Lagos', 'state' => 'Lagos', 'country' => 'Nigeria', 'postal_code' => '100001', 'whatsapp' => '+2348000000000', 'notes' => 'Requested training brochure'];
    }

    private function preview(string $csv, string $type = 'lead')
    {
        return $this->postJson('/admin/erp/contacts/import/preview', ['file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv), 'type' => $type]);
    }

    public function test_complete_contact_create_edit_filter_and_activity_journey(): void
    {
        $this->admin();
        $this->post('/admin/erp/contacts', $this->contact())->assertRedirect()->assertSessionHasNoErrors();
        $contact = CrmContact::firstOrFail();
        $this->assertDatabaseHas('crm_contacts', $this->contact());
        $this->put("/admin/erp/contacts/{$contact->id}", $this->contact(['type' => 'partner', 'address_line_2' => 'Floor 2', 'status' => 'qualified']))->assertSessionHasNoErrors();
        $this->get('/admin/erp/contacts?type=partner&search=Practice')->assertInertia(fn (Assert $p) => $p->has('contacts.data', 1)->where('contacts.data.0.address_line_2', 'Floor 2')->where('filters.type', 'partner'));
        $this->get('/admin/erp/contacts?type=customer')->assertInertia(fn (Assert $p) => $p->has('contacts.data', 0));
        $this->post('/admin/erp/activities', ['contact_id' => $contact->id, 'type' => 'call', 'subject' => 'Training follow-up', 'description' => 'Discussed the requested workshop'])->assertSessionHasNoErrors();
        $activity = $contact->activities()->firstOrFail();
        $this->put("/admin/erp/activities/{$activity->id}/complete")->assertSessionHasNoErrors();
        $this->get("/admin/erp/contacts/{$contact->id}")->assertInertia(fn (Assert $p) => $p->where('contact.activities.0.type', 'call')->where('contact.activities.0.subject', 'Training follow-up'));
        $this->assertNotNull($activity->fresh()->completed_at);
        $this->assertGreaterThanOrEqual(2, AuditLog::where('auditable_type', CrmContact::class)->count());
        $this->assertStringNotContainsString('Requested training brochure', AuditLog::all()->toJson());
        Mail::assertNothingSent();
    }

    public function test_import_defaults_and_per_row_types_are_atomic_and_replay_safe(): void
    {
        $this->admin();
        $csv = "first_name,last_name,email,type,company,source,address_line_1,city,country\nAda,Okoro,ADA@EXAMPLE.TEST,,Practice Ltd,Event,12 Learning Street,Lagos,Nigeria\nTunde,Ade,tunde@example.test,partner,Partner Ltd,Referral,,Abuja,Nigeria\n";
        $preview = $this->preview($csv, 'customer')->assertOk()->assertJsonPath('rows.0.type', 'customer')->assertJsonPath('rows.1.type', 'partner')->assertJsonPath('rows.0.email', 'ada@example.test');
        $this->assertDatabaseCount('crm_contacts', 0);
        $token = $preview->json('token');
        $this->post('/admin/erp/contacts/import', ['token' => $token])->assertSessionHasNoErrors();
        $this->post('/admin/erp/contacts/import', ['token' => $token])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('crm_contacts', 2);
        $this->assertDatabaseCount('crm_contact_imports', 1);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'ada@example.test', 'type' => 'customer', 'address_line_1' => '12 Learning Street', 'source' => 'Event']);
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_import_rejects_bad_rows_duplicates_unknown_columns_and_oversized_batches(): void
    {
        $this->admin();
        $this->preview("first_name,last_name,email,type\nAda,Okoro,ada@example.test,customer\nTunde,Ade,bad-email,admin\n")->assertUnprocessable()->assertJsonValidationErrors('Row 3');
        $this->preview("first_name,last_name,email\nAda,Okoro,ada@example.test\nTunde,Ade,ADA@example.test\n")->assertUnprocessable()->assertJsonValidationErrors('Row 3');
        $this->preview("first_name,last_name,email,user_id\nAda,Okoro,ada@example.test,1\n")->assertUnprocessable()->assertJsonValidationErrors('file');
        $rows = implode("\n", array_map(fn ($n) => "First,Last,person{$n}@example.test", range(1, 101)));
        $this->preview("first_name,last_name,email\n{$rows}")->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->assertDatabaseCount('crm_contacts', 0);
    }

    public function test_preview_is_actor_bound_expiring_tamper_proof_and_rechecks_changed_data(): void
    {
        $original = $this->admin();
        $token = $this->preview("first_name,last_name,email\nAda,Okoro,ada@example.test\n")->assertOk()->json('token');
        $this->admin();
        $this->postJson('/admin/erp/contacts/import', ['token' => $token])->assertUnprocessable();
        $this->actingAs($original);
        $this->postJson('/admin/erp/contacts/import', ['token' => $token.'tampered'])->assertUnprocessable();
        $this->travel(16)->minutes();
        $this->postJson('/admin/erp/contacts/import', ['token' => $token])->assertUnprocessable();
        $this->travelBack();
        $this->post('/admin/erp/contacts', $this->contact())->assertSessionHasNoErrors();
        $this->postJson('/admin/erp/contacts/import', ['token' => $token])->assertUnprocessable();
        $this->assertDatabaseCount('crm_contacts', 1);
        $this->assertDatabaseCount('crm_contact_imports', 0);
    }

    public function test_archived_email_is_not_reimported_and_edit_cannot_replace_another_email(): void
    {
        $this->admin();
        $service = app(CrmContactService::class);
        $first = $service->save($this->contact());
        $second = $service->save($this->contact(['email' => 'second@example.test']));
        $this->put("/admin/erp/contacts/{$second->id}", $this->contact())->assertSessionHasErrors('email');
        $this->delete("/admin/erp/contacts/{$first->id}")->assertRedirect();
        $this->preview("first_name,last_name,email\nAda,Okoro,ada@example.test\n")->assertUnprocessable();
        $this->assertSoftDeleted('crm_contacts', ['id' => $first->id]);
    }

    public function test_only_active_platform_admin_can_access_contact_data_or_import(): void
    {
        foreach (['learner', 'facilitator', 'org_admin', 'sponsor', 'sme_reviewer'] as $role) {
            $user = User::factory()->create();
            $user->assignRole(Role::findOrCreate($role, 'web'));
            $this->actingAs($user);
            $this->getJson('/admin/erp/contacts')->assertForbidden();
            $this->getJson('/admin/erp/contacts/import/template')->assertForbidden();
            $this->preview("first_name,last_name,email\nAda,Okoro,ada@example.test")->assertForbidden();
            $this->postJson('/admin/erp/contacts/import', ['token' => 'invalid'])->assertForbidden();
            $this->postJson('/admin/erp/contacts', $this->contact())->assertForbidden();
        }
        $admin = $this->admin();
        $admin->update(['is_active' => false]);
        $this->get('/admin/erp/contacts')->assertForbidden();
    }
}
