<?php

namespace Tests\Feature;

use App\Jobs\ProcessContactImport;
use App\Models\CrmContact;
use App\Models\CrmContactImport;
use App\Models\User;
use App\Services\BackgroundContactImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BackgroundContactImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake();
        Mail::fake();
    }

    private function admin(): User
    {
        $actor = User::factory()->create();
        $actor->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($actor);

        return $actor;
    }

    private function upload(string $csv, ?string $id = null, string $type = 'customer')
    {
        return $this->postJson('/admin/erp/contacts/imports', [
            'request_id' => $id ?? (string) Str::uuid(), 'type' => $type,
            'file' => UploadedFile::fake()->createWithContent('contacts.csv', $csv),
        ]);
    }

    private function finish(CrmContactImport $batch): void
    {
        for ($i = 0; $i < 30 && in_array($batch->fresh()->status, ['queued', 'processing']); $i++) {
            app(BackgroundContactImportService::class)->process($batch->id, $batch->fresh()->byte_offset);
        }
        $this->assertContains($batch->fresh()->status, ['completed', 'completed_with_errors']);
    }

    public function test_large_csv_is_queued_and_chunked_with_durable_progress_and_no_inline_import(): void
    {
        $actor = $this->admin();
        $csv = "first_name,last_name,email,type,notes\n";
        for ($i = 1; $i <= 601; $i++) {
            $csv .= "Person,Demo,bulk{$i}@example.test,".($i === 1 ? 'partner' : '').','.str_repeat('A', 1000)."\n";
        }
        $this->assertGreaterThan(512 * 1024, strlen($csv));
        $response = $this->upload($csv)->assertStatus(202)->assertJsonPath('import.status', 'queued')->assertJsonPath('import.imported_count', 0);
        $this->assertDatabaseCount('crm_contacts', 0);
        $batch = CrmContactImport::findOrFail($response->json('import.id'));
        Queue::assertPushed(ProcessContactImport::class, fn ($job) => $job->connection === 'crm_imports' && $job->queue === 'contact-imports');
        $offset = $batch->byte_offset;
        app(BackgroundContactImportService::class)->process($batch->id, $offset);
        $this->assertSame(100, $batch->fresh()->imported_count);
        app(BackgroundContactImportService::class)->process($batch->id, $offset);
        $this->assertDatabaseCount('crm_contacts', 100);
        $this->finish($batch);
        $this->assertSame(601, $batch->fresh()->imported_count);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'bulk1@example.test', 'type' => 'partner', 'assigned_to' => $actor->id]);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'bulk601@example.test', 'type' => 'customer']);
        Storage::disk('local')->assertMissing($batch->path);
        $this->getJson('/admin/erp/contacts/imports')->assertOk()->assertJsonPath('imports.0.progress', 100)->assertJsonMissingPath('imports.0.path')->assertJsonMissingPath('imports.0.content_hash');
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_bad_and_duplicate_rows_are_reported_while_valid_rows_continue(): void
    {
        $this->admin();
        $csv = "first_name,last_name,email,type,notes\nAda,Demo,ada@example.test,partner,\nAgain,Demo,ADA@EXAMPLE.TEST,,\nBad,Demo,invalid,lead,\nMulti,Line,multi@example.test,,\"Two lines\nwith comma, quoted\"\nWrong,Columns\n";
        $id = $this->upload($csv)->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $this->finish($batch);
        $this->assertSame('completed_with_errors', $batch->fresh()->status);
        $this->assertSame([5, 2, 1, 2], array_values($batch->fresh()->only(['processed_count', 'imported_count', 'skipped_count', 'invalid_count'])));
        $this->assertDatabaseHas('crm_contacts', ['email' => 'multi@example.test', 'notes' => "Two lines\nwith comma, quoted"]);
        $this->get('/admin/erp/contacts/imports/'.$id.'/errors')->assertOk()->assertDownload()->assertStreamedContent("\"Record (including header)\",Result,Reason\n3,duplicate,\"Email already exists, is archived, or appeared earlier in this file. Existing contacts were preserved.\"\n4,invalid,\"The email field must be a valid email address.\"\n6,invalid,\"The record exceeds 64 KB or its column count differs from the header.\"\n");
    }

    public function test_upload_retry_reuses_receipt_but_not_for_a_different_file_or_owner(): void
    {
        $this->admin();
        $id = (string) Str::uuid();
        $csv = "first_name,last_name,email\nAda,Demo,ada@example.test\n";
        $this->upload($csv, $id)->assertAccepted();
        $this->upload($csv, $id)->assertAccepted();
        Queue::assertPushed(ProcessContactImport::class, 1);
        $this->upload($csv, $id, 'partner')->assertUnprocessable();
        $this->admin();
        $this->upload($csv, $id)->assertNotFound();
        $this->get('/admin/erp/contacts/imports/'.$id.'/errors')->assertNotFound();
        $this->postJson('/admin/erp/contacts/imports/'.$id.'/cancel')->assertNotFound();
        $this->postJson('/admin/erp/contacts/imports/'.$id.'/retry')->assertNotFound();
        $this->getJson('/admin/erp/contacts/imports')->assertJsonCount(0, 'imports');
    }

    public function test_revoked_admin_and_cancellation_stop_remaining_chunks(): void
    {
        $actor = $this->admin();
        config(['crm.import_batch_size' => 1]);
        $csv = "first_name,last_name,email\nAda,Demo,ada@example.test\nBen,Demo,ben@example.test\n";
        $id = $this->upload($csv)->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $service = app(BackgroundContactImportService::class);
        $service->process($id, $batch->byte_offset);
        $actor->removeRole('platform_admin');
        $service->process($id, $batch->fresh()->byte_offset);
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertDatabaseCount('crm_contacts', 1);
        $this->admin();
        $second = $this->upload($csv)->assertAccepted()->json('import.id');
        $this->postJson('/admin/erp/contacts/imports/'.$second.'/cancel')->assertOk();
        $service->process($second, CrmContactImport::find($second)->byte_offset);
        $this->assertDatabaseCount('crm_contacts', 1);
    }

    public function test_failed_chunk_rolls_back_then_resumes_without_repeating_committed_contacts(): void
    {
        $this->admin();
        $id = $this->upload("first_name,last_name,email\nAda,Demo,ada@example.test\nBen,Demo,ben@example.test\n")->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $fail = true;
        Event::listen('eloquent.creating: '.CrmContact::class, function ($contact) use (&$fail) {
            if ($fail && $contact->email === 'ben@example.test') {
                throw new \RuntimeException('Simulated interruption');
            }
        });
        try {
            app(BackgroundContactImportService::class)->process($id, $batch->byte_offset);
            $this->fail('Expected interruption');
        } catch (\RuntimeException $error) {
            $this->assertSame('Simulated interruption', $error->getMessage());
        }
        $this->assertDatabaseCount('crm_contacts', 0);
        $this->assertSame(0, $batch->fresh()->imported_count);
        (new ProcessContactImport($id, $batch->byte_offset))->failed(null);
        $this->postJson('/admin/erp/contacts/imports/'.$id.'/retry')->assertOk()->assertJsonPath('import.status', 'queued');
        $fail = false;
        $this->finish($batch);
        $this->assertDatabaseCount('crm_contacts', 2);
    }

    public function test_upload_validation_and_permissions_apply_before_storage_or_queueing(): void
    {
        $this->admin();
        $this->upload("first_name,last_name,email,user_id\nAda,Demo,ada@example.test,1")->assertUnprocessable();
        $this->postJson('/admin/erp/contacts/imports', ['file' => UploadedFile::fake()->create('large.csv', 51201, 'text/csv'), 'request_id' => (string) Str::uuid(), 'type' => 'lead'])->assertUnprocessable();
        $learner = User::factory()->create();
        $learner->assignRole(Role::findOrCreate('learner', 'web'));
        $this->actingAs($learner);
        $this->getJson('/admin/erp/contacts/imports')->assertForbidden();
        $this->upload("first_name,last_name,email\nAda,Demo,ada@example.test")->assertForbidden();
        $this->assertDatabaseCount('crm_contact_import_batches', 0);
        Queue::assertNothingPushed();
    }

    public function test_retention_keeps_running_files_and_rejects_retry_after_source_expiry(): void
    {
        $this->admin();
        $csv = "first_name,last_name,email\nAda,Demo,ada@example.test\n";
        $id = $this->upload($csv)->assertAccepted()->json('import.id');
        $active = $this->upload($csv)->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $batch->update(['status' => 'failed', 'finished_at' => now()->subDays(8)]);
        $this->artisan('crm:cleanup-imports')->assertSuccessful();
        Storage::disk('local')->assertMissing($batch->path);
        Storage::disk('local')->assertExists(CrmContactImport::find($active)->path);
        $this->postJson('/admin/erp/contacts/imports/'.$id.'/retry')->assertUnprocessable();
    }

    public function test_oversized_quoted_record_is_skipped_without_losing_next_record(): void
    {
        $this->admin();
        $csv = "first_name,last_name,email,notes\nLarge,Demo,large@example.test,\"".str_repeat("A,\n", 30000)."\"\nGood,Demo,good@example.test,\"Includes \"\"quoted\"\" text\"\n";
        $id = $this->upload($csv)->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $this->finish($batch);
        $this->assertSame(1, $batch->fresh()->invalid_count);
        $this->assertSame(1, $batch->fresh()->imported_count);
        $this->assertDatabaseHas('crm_contacts', ['email' => 'good@example.test', 'notes' => 'Includes "quoted" text']);
        $this->assertDatabaseMissing('crm_contacts', ['email' => 'large@example.test']);
    }

    public function test_inactive_account_cannot_continue_an_already_queued_import(): void
    {
        $actor = $this->admin();
        $id = $this->upload("first_name,last_name,email\nAda,Demo,ada@example.test\n")->assertAccepted()->json('import.id');
        $batch = CrmContactImport::findOrFail($id);
        $actor->update(['is_active' => false]);
        app(BackgroundContactImportService::class)->process($id, $batch->byte_offset);
        $this->assertSame('cancelled', $batch->fresh()->status);
        $this->assertDatabaseCount('crm_contacts', 0);
    }
}
