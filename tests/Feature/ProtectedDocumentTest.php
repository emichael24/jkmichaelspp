<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\ProtectedDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;
use ZipArchive;

class ProtectedDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function lessonDocument(): array
    {
        Storage::fake('local');
        $author = User::factory()->create();
        Role::findOrCreate('facilitator', 'web');
        $author->assignRole('facilitator');
        $asset = MediaAsset::create(['id' => (string) Str::uuid(), 'user_id' => $author->id, 'kind' => 'document', 'path' => 'private/guide.pdf', 'mime' => 'application/pdf', 'bytes' => 9, 'sha256' => str_repeat('a', 64)]);
        Storage::disk('local')->put($asset->path, '%PDF-1.4');
        $course = Course::factory()->create(['author_id' => $author->id, 'is_published' => true]);
        $module = $course->modules()->create(['title' => 'Protected documents', 'is_published' => true]);
        $lesson = $module->lessons()->create(['title' => 'Read in your lesson', 'content_type' => 'pdf', 'pdf_url' => $asset->url(), 'is_published' => true, 'is_downloadable' => false]);
        $learner = User::factory()->create();
        $enrollment = $course->enrollments()->create(['user_id' => $learner->id, 'status' => 'active', 'enrolled_at' => now()]);

        return [$asset, $lesson, $learner, $enrollment, $author];
    }

    private function fakeRendering(): void
    {
        $this->partialMock(ProtectedDocumentService::class, function ($mock) {
            $mock->shouldReceive('serverRenderingAvailable')->andReturn(true);
            $mock->shouldReceive('metadata')->andReturn(['pages' => 2, 'format' => 'pdf']);
            $mock->shouldReceive('page')->andReturn('rendered-watermarked-page');
        });
    }

    public function test_protected_reader_denies_originals_and_rechecks_entitlement_on_every_request(): void
    {
        [$asset, $lesson, $learner, $enrollment] = $this->lessonDocument();
        $this->fakeRendering();
        $reader = "/lessons/{$lesson->id}/documents/pdf";
        $response = $this->actingAs($learner)->getJson($reader)->assertOk()->assertJsonPath('pages', 2)->assertJsonPath('download_url', null);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', $response->headers->get('Cache-Control'));
        $this->get($reader.'/pages/1')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->get($asset->url())->assertForbidden();
        $this->get($asset->url().'?download=1')->assertForbidden();
        $this->withHeader('Range', 'bytes=0-4')->get($asset->url())->assertForbidden();
        $this->get("/lessons/{$lesson->id}/download/pdf")->assertForbidden();
        $this->actingAs(User::factory()->create())->getJson($reader)->assertForbidden();
        $this->get($reader.'/pages/1')->assertForbidden();
        $enrollment->update(['status' => 'suspended']);
        $this->actingAs($learner)->getJson($reader)->assertForbidden();
        $this->get($reader.'/pages/2')->assertForbidden();
    }

    public function test_reader_falls_back_to_browser_rendering_when_converters_are_missing(): void
    {
        [$asset, $lesson, $learner, $enrollment] = $this->lessonDocument();
        config(['documents.renderer' => 'browser']);
        $reader = "/lessons/{$lesson->id}/documents/pdf";
        $this->actingAs($learner)->getJson($reader)->assertOk()
            ->assertJsonPath('mode', 'browser')->assertJsonPath('format', 'pdf')
            ->assertJsonPath('source_url', url($reader.'/source'))->assertJsonPath('download_url', null)
            ->assertJson(fn ($json) => $json->where('watermark', fn ($mark) => str_contains($mark, 'Viewer #'.$learner->id))->etc());
        // Plain navigation (save link / address bar) is refused; the reader's XHR is allowed.
        $this->get($reader.'/source')->assertForbidden();
        $source = $this->withHeader('X-Document-Reader', '1')->get($reader.'/source')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')->assertHeader('Cross-Origin-Resource-Policy', 'same-origin');
        $this->assertStringContainsString('no-store', $source->headers->get('Cache-Control'));
        $this->actingAs(User::factory()->create())->withHeader('X-Document-Reader', '1')->get($reader.'/source')->assertForbidden();
        $enrollment->update(['status' => 'suspended']);
        $this->actingAs($learner)->withHeader('X-Document-Reader', '1')->get($reader.'/source')->assertForbidden();
        $enrollment->update(['status' => 'active']);
        config(['documents.renderer' => 'server']);
        $this->withHeader('X-Document-Reader', '1')->get($reader.'/source')->assertNotFound();
    }

    public function test_auto_renderer_uses_the_browser_when_server_rendering_is_unavailable(): void
    {
        [, $lesson, $learner] = $this->lessonDocument();
        config(['documents.renderer' => 'auto', 'documents.pdfinfo' => 'jk-missing-pdfinfo', 'documents.pdftoppm' => 'jk-missing-pdftoppm']);
        $this->actingAs($learner)->getJson("/lessons/{$lesson->id}/documents/pdf")->assertOk()->assertJsonPath('mode', 'browser');
    }

    public function test_source_access_requires_explicit_download_permission_and_it_can_be_revoked(): void
    {
        [$asset, $lesson, $learner] = $this->lessonDocument();
        $lesson->update(['is_downloadable' => true]);
        $this->actingAs($learner)->get($asset->url().'?download=1')->assertOk()->assertDownload('resource.pdf');
        $lesson->update(['is_downloadable' => false]);
        $this->get($asset->url())->assertForbidden();
        $asset->update(['mime' => ProtectedDocumentService::PPTX]);
        $lesson->update(['slides_url' => $asset->url(), 'pdf_url' => null, 'content_type' => 'slides']);
        $this->get($asset->url())->assertForbidden();
        $this->get("/lessons/{$lesson->id}/download/slides")->assertForbidden();
    }

    public function test_author_preview_is_owned_and_draft_reader_does_not_bypass_learner_locks(): void
    {
        [$asset, $lesson, $learner, , $author] = $this->lessonDocument();
        $this->fakeRendering();
        $this->actingAs($author)->getJson('/authoring/documents/'.$asset->id)->assertOk()->assertJsonPath('download_url', null);
        $this->get('/authoring/documents/'.$asset->id.'/pages/1')->assertOk();
        $other = User::factory()->create();
        $other->assignRole('facilitator');
        $this->actingAs($other)->getJson('/authoring/documents/'.$asset->id)->assertForbidden();
        $this->get('/authoring/documents/'.$asset->id.'/pages/1')->assertForbidden();
        $this->actingAs($learner)->getJson('/authoring/documents/'.$asset->id)->assertForbidden();
        $lesson->update(['unlock_type' => 'date', 'unlock_at' => now()->addDay()]);
        $this->getJson("/lessons/{$lesson->id}/documents/pdf")->assertForbidden();
        $lesson->update(['is_published' => false]);
        $this->getJson("/lessons/{$lesson->id}/documents/pdf")->assertNotFound();
    }

    public function test_external_document_links_fail_closed_and_cannot_be_saved_as_protected_uploads(): void
    {
        [$asset, $lesson, $learner, , $author] = $this->lessonDocument();
        $lesson->update(['pdf_url' => 'https://example.test/guide.pdf']);
        $this->actingAs($learner)->getJson("/lessons/{$lesson->id}/documents/pdf")->assertUnprocessable();
        $course = $lesson->module->course;
        $course->update(['is_published' => false, 'published_at' => null, 'approval_status' => 'draft']);
        $this->actingAs($author)->postJson("/facilitator/courses/{$course->id}/modules/{$lesson->module_id}/lessons", ['title' => 'Public source', 'content_type' => 'pdf', 'pdf_url' => 'https://example.test/guide.pdf'])->assertUnprocessable()->assertJsonValidationErrors('pdf_url');
    }

    public function test_presentations_reject_external_media_active_content_and_archive_traversal(): void
    {
        $service = app(ProtectedDocumentService::class);
        foreach (['../escape.xml' => '<x/>', 'ppt/embeddings/unsafe.bin' => 'active', 'ppt/_rels/slide1.xml.rels' => '<Relationships><Relationship TargetMode="External" Type="image" Target="http://127.0.0.1/private"/></Relationships>'] as $name => $content) {
            $path = tempnam(sys_get_temp_dir(), 'pptx');
            $zip = new ZipArchive;
            $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
            $zip->addFromString('[Content_Types].xml', '<Types>application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml</Types>');
            $zip->addFromString('ppt/presentation.xml', '<presentation/>');
            $zip->addFromString($name, $content);
            $zip->close();
            try {
                $service->validatePresentation($path);
                $this->fail('Unsafe presentation accepted.');
            } catch (HttpException $exception) {
                $this->assertSame(422, $exception->getStatusCode());
            } finally {
                unlink($path);
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'pptx');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('[Content_Types].xml', '<Types>application/vnd.openxmlformats-officedocument.presentationml.presentation.main+xml</Types>');
        $zip->addFromString('ppt/presentation.xml', '<presentation/>');
        $zip->addFromString('_rels/.rels', '<Relationships/>');
        $zip->close();
        try {
            $service->validatePresentation($path);
            $this->addToAssertionCount(1);
        } finally {
            unlink($path);
        }
    }
}
