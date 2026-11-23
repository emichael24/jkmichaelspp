<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\EmailTemplate;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\EmailBranding;
use App\Services\NotificationDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class EmailDesignTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create(['first_name' => 'Toyin']);
        $user->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($user);

        return $user;
    }

    public function test_admin_brands_emails_with_logo_colours_and_footer(): void
    {
        Storage::fake('public');
        $this->admin();
        $this->get('/admin/erp/email-design')->assertInertia(fn (Assert $page) => $page
            ->component('Admin/ERP/Messaging/EmailDesign')->has('templates', 6)->where('branding.accent_color', EmailBranding::DEFAULT_ACCENT));

        $this->post('/admin/erp/email-design/branding', ['brand_name' => 'JK Michaels Institute', 'accent_color' => '#123ABC', 'header_background' => '#0F172A',
            'footer_text' => "12 Example Road\nLagos", 'logo' => UploadedFile::fake()->image('logo.png', 480, 96)])->assertSessionHasNoErrors();
        $branding = app(EmailBranding::class)->get();
        $this->assertSame('#123abc', $branding['accent_color']);
        Storage::disk('public')->assertExists($branding['logo_path']);

        $html = $this->postJson('/admin/erp/email-design/preview', ['body' => '<p>Hi {{first_name}},</p><script>x</script>'])->assertOk()->json('html');
        $this->assertStringContainsString($branding['logo_url'], $html);
        $this->assertStringContainsString('12 Example Road<br />', $html);
        $this->assertStringContainsString('Hi Toyin,', $html);
        $this->assertStringNotContainsString('<script>', $html);

        $this->post('/admin/erp/email-design/branding', ['brand_name' => 'JK', 'accent_color' => 'red', 'header_background' => '#ffffff'])->assertSessionHasErrors('accent_color');
        $old = $branding['logo_path'];
        $this->post('/admin/erp/email-design/branding', ['brand_name' => 'JK', 'accent_color' => '#123abc', 'header_background' => '#ffffff', 'remove_logo' => true])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $this->assertNull(app(EmailBranding::class)->get()['logo_url']);
    }

    public function test_templates_are_sanitized_listed_and_restricted_to_admins(): void
    {
        $this->admin();
        $this->post('/admin/erp/email-templates', ['name' => 'Promo', 'category' => 'announcement', 'subject' => 'Hello {{first_name}}',
            'body' => '<h2 onclick="x()">Big news</h2><script>alert(1)</script><p>Read more</p>'])->assertSessionHasNoErrors();
        $template = EmailTemplate::where('name', 'Promo')->firstOrFail();
        $this->assertSame('<h2>Big news</h2><p>Read more</p>', $template->body);
        $this->getJson('/admin/erp/email-templates')->assertOk()->assertJsonCount(7, 'templates');
        $this->put("/admin/erp/email-templates/{$template->id}", ['name' => 'Promo 2', 'category' => 'bogus', 'subject' => 'x', 'body' => '<p>x</p>'])->assertSessionHasErrors('category');
        $this->delete("/admin/erp/email-templates/{$template->id}")->assertSessionHasNoErrors();
        $this->assertModelMissing($template);

        $learner = User::factory()->create();
        $this->actingAs($learner)->getJson('/admin/erp/email-design')->assertForbidden();
        $this->getJson('/admin/erp/email-templates')->assertForbidden();
    }

    public function test_test_send_goes_only_to_the_signed_in_admin(): void
    {
        Mail::fake();
        $admin = $this->admin();
        $this->postJson('/admin/erp/email-design/test', ['subject' => 'Hi {{first_name}}', 'body' => '<p>Hello {{first_name}}</p>'])->assertOk();
        $this->postJson('/admin/erp/email-design/test', ['subject' => 'Hi', 'body' => ''])->assertUnprocessable();
    }

    public function test_contact_emails_are_branded_and_personalized(): void
    {
        $this->admin();
        app(EmailBranding::class)->update(['brand_name' => 'JK Michaels Institute', 'accent_color' => '#0055aa', 'header_background' => '#ffffff', 'footer_text' => 'Lagos, Nigeria']);
        $contact = CrmContact::create(['uuid' => (string) Str::uuid(), 'first_name' => 'Chidi <b>', 'last_name' => 'Okafor', 'email' => 'chidi@example.test',
            'type' => 'lead', 'status' => 'new', 'marketing_email' => true]);
        $this->post('/admin/erp/messaging/quick-send', ['recipient_type' => 'contact', 'recipient_id' => $contact->id, 'channel' => 'email',
            'subject' => '{{first_name}}, your brochure', 'message' => '<p>Dear {{first_name}}, see <em>our courses</em>.</p>', 'body_format' => 'html'])->assertSessionHasNoErrors();
        $row = NotificationDelivery::firstOrFail();
        $this->travelTo($row->available_at->copy()->addSecond());
        app(NotificationDeliveryService::class)->deliver($row->id);

        $email = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame('Chidi <b>, your brochure', $email->getSubject());
        $html = $email->getHtmlBody();
        $this->assertStringContainsString('Dear Chidi &lt;b&gt;, see <em>our courses</em>.', $html);
        $this->assertStringContainsString('JK Michaels Institute', $html);
        $this->assertStringContainsString('#0055aa', $html);
        $this->assertStringContainsString('Lagos, Nigeria', $html);
        $this->assertStringContainsString('Manage your message preferences', $html);
        $this->assertStringContainsString('Dear Chidi <b>, see our courses.', $email->getTextBody());
    }

    public function test_plain_notifications_use_the_branded_layout(): void
    {
        $user = User::factory()->create(['first_name' => 'Ngozi']);
        app(EmailBranding::class)->update(['brand_name' => 'JK Michaels Institute', 'accent_color' => '#0055aa', 'header_background' => '#ffffff', 'footer_text' => '']);
        $row = NotificationDelivery::create(['event_key' => hash('sha256', 'n1'), 'user_id' => $user->id, 'channel' => 'mail', 'category' => 'learning',
            'payload' => ['title' => 'Your work was reviewed', 'body' => "Line one\n\nLine two", 'action_url' => '/dashboard'], 'available_at' => now()]);
        app(NotificationDeliveryService::class)->deliver($row->id);
        $html = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('<h3>Your work was reviewed</h3><p>Line one</p><p>Line two</p>', $html);
        $this->assertStringContainsString(rtrim(config('app.url'), '/').'/dashboard', $html);
    }
}
