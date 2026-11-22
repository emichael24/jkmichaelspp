<?php

namespace Tests\Feature;

use App\Models\CrmContact;
use App\Models\CrmOrganization;
use App\Models\DirectMessage;
use App\Models\MessagingCampaign;
use App\Models\NotificationDelivery;
use App\Models\Organization;
use App\Models\User;
use App\Services\CampaignAudienceService;
use App\Services\CampaignDeliveryService;
use App\Services\CommunityAccessService;
use App\Services\EmailContent;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkMessagingTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate('platform_admin', 'web'));
        $this->actingAs($user);

        return $user;
    }

    private function user(array $values = [], string $role = 'learner'): User
    {
        $user = User::factory()->create($values);
        $user->assignRole(Role::findOrCreate($role, 'web'));
        app(NotificationService::class)->updatePreferences($user, ['marketing_email' => true, 'marketing_whatsapp' => true]);

        return $user;
    }

    private function contact(array $values = []): CrmContact
    {
        return CrmContact::create($values + ['uuid' => (string) Str::uuid(), 'first_name' => 'Ada', 'last_name' => 'Example',
            'email' => Str::random(8).'@example.test', 'type' => 'lead', 'status' => 'new', 'marketing_email' => true]);
    }

    private function campaign(array $values = []): MessagingCampaign
    {
        return MessagingCampaign::create($values + ['uuid' => (string) Str::uuid(), 'created_by' => auth()->id(), 'name' => 'Training news',
            'channel' => 'email', 'type' => 'broadcast', 'subject' => 'Welcome', 'body' => '<p>Hello <strong>team</strong>.</p>', 'body_format' => 'html', 'status' => 'draft']);
    }

    private function deliverAll(): void
    {
        foreach (NotificationDelivery::all() as $delivery) {
            $this->travelTo($delivery->available_at->copy()->addSecond());
            app(NotificationDeliveryService::class)->deliver($delivery->id);
        }
    }

    public function test_admin_preview_filters_user_roles_and_organizations_without_sending(): void
    {
        $this->admin();
        $organization = Organization::create(['name' => 'Company A', 'contact_email' => 'company-a@example.test', 'is_active' => true]);
        $learner = $this->user(['organization_id' => $organization->id]);
        $this->user(['organization_id' => $organization->id], 'org_admin');
        $this->user();
        $this->postJson('/admin/erp/messaging/audience-preview', ['channel' => 'email', 'audience_filter' => ['source' => 'users', 'role' => 'learner', 'organization_id' => $organization->id]])
            ->assertOk()->assertJsonPath('eligible', 1)->assertJsonPath('sample.0.recipient', $learner->email);
        $this->postJson('/admin/erp/messaging/audience-preview', ['channel' => 'inbox', 'audience_filter' => ['organization_members' => true]])
            ->assertOk()->assertJsonPath('eligible', 2);
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->get('/admin/erp/messaging?compose=1&audience=crm&contact_type=alumni')->assertInertia(fn (Assert $page) => $page
            ->where('compose', true)->where('initialAudience.source', 'crm')->where('initialAudience.contact_types.0', 'alumni')->has('audienceOptions.roles', 3));
    }

    public function test_crm_types_and_all_audiences_deduplicate_and_preserve_html_email(): void
    {
        $this->admin();
        $account = $this->user();
        $this->contact(['email' => strtoupper($account->email)]);
        $alumni = $this->contact(['type' => 'alumni']);
        $this->contact(['marketing_email' => false]);
        $this->contact(['status' => 'inactive']);
        $audiences = app(CampaignAudienceService::class);
        $preview = $audiences->each(['source' => 'all'], 'mail');
        $this->assertSame(2, $preview['eligible']);
        $this->assertSame(1, $preview['duplicates']);
        $this->assertSame(1, $audiences->each(['source' => 'crm', 'contact_types' => ['alumni']], 'mail')['eligible']);
        $campaign = $this->campaign(['audience_filter' => ['source' => 'all']]);
        $this->assertSame(2, app(CampaignDeliveryService::class)->launch($campaign));
        $this->assertSame(2, app(CampaignDeliveryService::class)->launch($campaign));
        $this->deliverAll();
        $messages = Mail::mailer()->getSymfonyTransport()->messages();
        $this->assertCount(2, $messages);
        foreach ($messages as $sent) {
            $email = $sent->getOriginalMessage();
            $this->assertStringContainsString('<strong>team</strong>', $email->getHtmlBody());
            $this->assertStringContainsString('Hello team.', $email->getTextBody());
            $this->assertCount(1, $email->getTo());
            $this->assertEmpty($email->getCc());
        }
        $this->assertNotNull($alumni->fresh()->last_contacted_at);
        $this->assertSame('sent', $campaign->fresh()->status);
    }

    public function test_campaign_store_sanitizes_rich_email_and_rejects_blank_content_and_invalid_filters(): void
    {
        $this->admin();
        $input = ['name' => 'Welcome', 'channel' => 'email', 'type' => 'broadcast', 'subject' => 'News', 'body_format' => 'html',
            'body' => '<script>alert(1)</script><h2 onclick="bad()">Welcome</h2><p><strong>Team</strong> <a href="javascript:alert(1)">unsafe</a> <a href="https://example.test/learn">Learn</a></p>'];
        $this->post('/admin/erp/messaging', $input)->assertRedirect()->assertSessionHasNoErrors();
        $campaign = MessagingCampaign::firstOrFail();
        $this->assertSame('html', $campaign->body_format);
        $this->assertStringContainsString('<h2>Welcome</h2>', $campaign->body);
        foreach (['script', 'onclick', 'javascript:'] as $bad) {
            $this->assertStringNotContainsString($bad, $campaign->body);
        }
        $this->postJson('/admin/erp/messaging', array_replace($input, ['body' => '<p>&nbsp;</p>']))->assertUnprocessable()->assertJsonValidationErrors('body');
        $this->postJson('/admin/erp/messaging', array_replace($input, ['channel' => 'inbox']))->assertUnprocessable()->assertJsonValidationErrors('body_format');
        $this->postJson('/admin/erp/messaging/audience-preview', ['channel' => 'email', 'audience_filter' => ['source' => 'crm', 'contact_types' => ['bogus']]])->assertUnprocessable();
        $this->assertSame("First\nSecond (https://example.test)", app(EmailContent::class)->text('<p>First</p><p><a href="https://example.test">Second</a></p>'));
    }

    public function test_crm_individual_email_accepts_contact_id_returns_inertia_redirect_and_delivers_rich_text(): void
    {
        $this->admin();
        $contact = $this->contact(['marketing_email' => false]);
        $this->from('/admin/erp/contacts')->post('/admin/erp/messaging/quick-send', ['recipient_type' => 'contact', 'recipient_id' => $contact->id,
            'channel' => 'email', 'subject' => 'Your requested brochure', 'message' => '<p>See <em>our courses</em>.</p>', 'body_format' => 'html'], ['X-Inertia' => 'true'])
            ->assertRedirect('/admin/erp/contacts')->assertSessionHasNoErrors();
        $row = NotificationDelivery::firstOrFail();
        $this->assertNull($row->user_id);
        $this->assertSame($contact->id, $row->contact_id);
        $this->assertSame('correspondence', $row->category);
        $this->deliverAll();
        $this->assertSame('sent', $row->fresh()->status);
        $email = Mail::mailer()->getSymfonyTransport()->messages()->first()->getOriginalMessage();
        $this->assertSame($contact->email, $email->getTo()[0]->getAddress());
        $this->assertStringContainsString('<em>our courses</em>', $email->getHtmlBody());
        $this->assertDatabaseHas('crm_activities', ['contact_id' => $contact->id, 'type' => 'email', 'direction' => 'outbound']);
    }

    public function test_revoked_consent_changed_address_and_archived_contacts_are_canceled_before_delivery(): void
    {
        $this->admin();
        $revoked = $this->contact();
        $changed = $this->contact();
        $archived = $this->contact();
        $campaign = $this->campaign(['audience_filter' => ['source' => 'crm']]);
        app(CampaignDeliveryService::class)->launch($campaign);
        $revoked->update(['marketing_email' => false]);
        $changed->update(['email' => 'different@example.test']);
        $archived->delete();
        $this->deliverAll();
        $this->assertSame(3, NotificationDelivery::where('status', 'canceled')->count());
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
        $this->assertSame(3, $campaign->fresh()->failed_count);
    }

    public function test_signed_crm_unsubscribe_requires_confirmation_and_stops_linked_account_campaigns(): void
    {
        $this->admin();
        $account = $this->user();
        $contact = $this->contact(['user_id' => $account->id, 'email' => $account->email]);
        $url = URL::signedRoute('messaging.unsubscribe', ['contact' => $contact->id, 'channel' => 'mail']);
        Auth::logout();
        $this->get($url)->assertOk();
        $this->assertTrue($contact->fresh()->marketing_email);
        $this->get('/message-preferences/'.$contact->id.'/mail')->assertForbidden();
        $this->post($url)->assertOk();
        $this->assertFalse($contact->fresh()->marketing_email);
        $this->assertFalse($account->fresh()->notificationPreferences->marketing_email);
        $this->assertSame(0, app(CampaignAudienceService::class)->each(['source' => 'all'], 'mail')['eligible']);
    }

    public function test_whatsapp_deduplicates_phone_formatting_and_delivers_crm_with_provider_acknowledgment(): void
    {
        $this->admin();
        config(['services.flexichurch.api_key' => 'test-key']);
        $first = $this->contact(['whatsapp' => '+234 800 000 0000', 'marketing_whatsapp' => true]);
        $this->contact(['whatsapp' => '002348000000000', 'marketing_whatsapp' => true]);
        $campaign = $this->campaign(['channel' => 'whatsapp', 'body_format' => 'text', 'body' => 'Training reminder', 'audience_filter' => ['source' => 'crm']]);
        $this->assertSame(1, app(CampaignDeliveryService::class)->launch($campaign));
        $this->mock(WhatsAppService::class)->shouldReceive('sendText')->once()->withArgs(fn ($phone, $message) => $phone === $first->whatsapp && str_contains($message, 'Training reminder') && str_contains($message, 'signature='))->andReturn(['messageId' => 'ack-123']);
        $this->deliverAll();
        $this->assertSame('sent', NotificationDelivery::first()->status);
        $this->assertSame('ack-123', NotificationDelivery::first()->provider_id);
    }

    public function test_inbox_broadcast_is_visible_across_organizations_without_exposing_other_recipients(): void
    {
        $admin = $this->admin();
        $org = Organization::create(['name' => 'Company B', 'contact_email' => 'company-b@example.test', 'is_active' => true]);
        $recipient = $this->user(['organization_id' => $org->id]);
        $other = $this->user();
        $blocked = $this->user();
        app(NotificationService::class)->updatePreferences($blocked, ['announcements' => false]);
        $campaign = $this->campaign(['channel' => 'inbox', 'body_format' => 'text', 'body' => 'Private announcement', 'audience_filter' => ['source' => 'users', 'role' => 'learner']]);
        $this->assertSame(2, app(CampaignDeliveryService::class)->launch($campaign));
        $this->deliverAll();
        $this->deliverAll();
        $this->assertDatabaseCount('direct_messages', 2);
        $this->assertSame(1, DirectMessage::unreadCountFor($recipient->id));
        $this->assertTrue(app(CommunityAccessService::class)->peers($recipient)->whereKey($admin->id)->exists());
        $this->assertFalse(app(CommunityAccessService::class)->peers($recipient)->whereKey($other->id)->exists());
        $this->actingAs($recipient)->get('/messages/'.$admin->id)->assertInertia(fn (Assert $page) => $page->has('messages', 1)->where('messages.0.recipient_id', $recipient->id));
        $this->actingAs($recipient)->get('/messages/'.$other->id)->assertNotFound();
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_crm_inbox_audience_requires_account_and_respects_quiet_hours_and_announcements(): void
    {
        $this->admin();
        $user = $this->user(['timezone' => 'UTC']);
        $this->contact(['email' => $user->email, 'user_id' => $user->id, 'marketing_email' => false]);
        $this->contact();
        $this->travelTo(now()->setTime(23, 0));
        app(NotificationService::class)->updatePreferences($user, ['quiet_hours_start' => '22:00', 'quiet_hours_end' => '08:00']);
        $campaign = $this->campaign(['channel' => 'inbox', 'body_format' => 'text', 'body' => 'Announcement', 'audience_filter' => ['source' => 'crm']]);
        $this->assertSame(1, app(CampaignDeliveryService::class)->launch($campaign));
        $delivery = NotificationDelivery::firstOrFail();
        $this->assertSame('08:00', $delivery->available_at->format('H:i'));
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertDatabaseCount('direct_messages', 0);
        app(NotificationService::class)->updatePreferences($user, ['announcements' => false]);
        $this->deliverAll();
        $this->assertSame('canceled', $delivery->fresh()->status);
    }

    public function test_inbox_http_launch_and_cancel_and_contact_consent_edit_are_supported(): void
    {
        $this->admin();
        $this->user();
        $this->post('/admin/erp/messaging', ['name' => 'Inbox update', 'channel' => 'inbox', 'type' => 'broadcast',
            'subject' => 'News', 'body' => 'Private update', 'body_format' => 'text', 'audience_filter' => ['role' => 'learner']])->assertRedirect()->assertSessionHasNoErrors();
        $campaign = MessagingCampaign::firstOrFail();
        $this->get('/admin/erp/messaging/'.$campaign->id)->assertInertia(fn (Assert $page) => $page->where('audiencePreview.eligible', 1));
        $this->post('/admin/erp/messaging/'.$campaign->id.'/send')->assertRedirect();
        $this->post('/admin/erp/messaging/'.$campaign->id.'/cancel')->assertRedirect();
        $this->deliverAll();
        $this->assertDatabaseCount('direct_messages', 0);
        $this->assertSame('cancelled', $campaign->fresh()->status);
        $contact = $this->contact();
        $this->put('/admin/erp/contacts/'.$contact->id, ['first_name' => 'Ada', 'last_name' => 'Example', 'email' => $contact->email,
            'type' => 'alumni', 'status' => 'qualified', 'marketing_email' => false, 'marketing_whatsapp' => true])->assertSessionHasNoErrors();
        $this->assertFalse($contact->fresh()->marketing_email);
        $this->assertTrue($contact->fresh()->marketing_whatsapp);
    }

    public function test_crm_organization_filter_and_linked_account_opt_out_cannot_be_bypassed(): void
    {
        $this->admin();
        $company = CrmOrganization::create(['name' => 'CRM Company']);
        $account = $this->user();
        $this->contact(['organization_id' => $company->id]);
        $this->contact();
        $this->contact(['organization_id' => $company->id, 'email' => $account->email]);
        app(NotificationService::class)->updatePreferences($account, ['marketing_email' => false]);
        $this->postJson('/admin/erp/messaging/audience-preview', ['channel' => 'email', 'audience_filter' => ['source' => 'crm', 'crm_organization_id' => $company->id]])
            ->assertOk()->assertJsonPath('eligible', 1)->assertJsonPath('excluded', 1);
    }

    public function test_non_admin_cannot_create_preview_launch_or_quick_send_bulk_messages(): void
    {
        $this->admin();
        $campaign = $this->campaign();
        $user = $this->user();
        $this->actingAs($user)->getJson('/admin/erp/messaging')->assertForbidden();
        foreach (['/admin/erp/messaging', '/admin/erp/messaging/audience-preview', '/admin/erp/messaging/'.$campaign->id.'/send', '/admin/erp/messaging/quick-send'] as $endpoint) {
            $this->postJson($endpoint, ['channel' => 'email'])->assertForbidden();
        }
        $this->assertDatabaseCount('notification_deliveries', 0);
    }

    public function test_scheduled_crm_campaign_dispatches_once_and_respects_frequency_limits(): void
    {
        $this->admin();
        $contact = $this->contact();
        $campaign = $this->campaign(['audience_filter' => ['source' => 'crm'], 'status' => 'scheduled', 'scheduled_at' => now()->addHour()]);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertDatabaseCount('notification_deliveries', 0);
        $this->travelTo(now()->addHours(2));
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertSame('sent', $campaign->fresh()->status);
        $this->artisan('notifications:dispatch')->assertSuccessful();
        $this->assertCount(1, Mail::mailer()->getSymfonyTransport()->messages());
        config(['notifications.daily_message_limit' => 1]);
        $second = $this->campaign(['audience_filter' => ['source' => 'crm']]);
        app(CampaignDeliveryService::class)->launch($second);
        $delivery = NotificationDelivery::where('campaign_id', $second->id)->firstOrFail();
        app(NotificationDeliveryService::class)->deliver($delivery->id);
        $this->assertSame('frequency_limit', $delivery->fresh()->error_code);
        $this->assertSame('pending', $delivery->fresh()->status);
    }
}
