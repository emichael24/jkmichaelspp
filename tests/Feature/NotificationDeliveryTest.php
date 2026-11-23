<?php

namespace Tests\Feature;

use App\Models\MessagingCampaign;
use App\Models\NotificationDelivery;
use App\Models\User;
use App\Services\CampaignDeliveryService;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_repeated_domain_events_create_one_mail_delivery_and_worker_replays_do_not_resend(): void
    {
        config(['mail.default' => 'array']);
        $user = User::factory()->create();
        $notifications = app(NotificationService::class);
        $notifications->sendPaymentReceipt($user, 'order-123', 100, 'NGN');
        $notifications->sendPaymentReceipt($user, 'order-123', 100, 'NGN');
        $this->assertSame(1, DB::table('notifications')->count());
        $this->assertSame(1, NotificationDelivery::count());
        $row = NotificationDelivery::firstOrFail();
        $worker = app(NotificationDeliveryService::class);
        $this->assertStringNotContainsString('Payment Received', DB::table('notification_deliveries')->value('payload'));
        $worker->deliver($row->id);
        $worker->deliver($row->id);
        $this->assertSame('sent', $row->fresh()->status);
        $this->assertSame(1, Mail::mailer()->getSymfonyTransport()->messages()->count());
        $this->assertSame(1, $row->fresh()->attempts);
    }

    public function test_digest_respects_local_quiet_hours_and_withdrawn_consent(): void
    {
        config(['mail.default' => 'array']);
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(21, 30));
        $user = User::factory()->create(['timezone' => 'Africa/Lagos']);
        $notifications = app(NotificationService::class);
        $notifications->updatePreferences($user, ['frequency' => 'daily_digest', 'quiet_hours_start' => '22:00', 'quiet_hours_end' => '10:00']);
        $notifications->send($user, 'learning', ['title' => 'First update', 'body' => 'First']);
        $notifications->send($user, 'learning', ['title' => 'Second update', 'body' => 'Second']);
        $rows = NotificationDelivery::all();
        $this->assertSame($rows[0]->group_key, $rows[1]->group_key);
        $this->assertSame('2026-10-06 09:00:00', $rows[0]->available_at->format('Y-m-d H:i:s'));
        $this->travelTo($rows[0]->available_at->copy()->addSecond());
        app(NotificationDeliveryService::class)->deliver($rows[0]->id);
        $this->assertSame(2, NotificationDelivery::where('status', 'sent')->count());
        $this->assertSame(1, Mail::mailer()->getSymfonyTransport()->messages()->count());
        $notifications->send($user, 'learning', ['title' => 'Third update']);
        $third = NotificationDelivery::where('status', 'pending')->firstOrFail();
        $notifications->updatePreferences($user, ['email_enabled' => false]);
        $this->travelTo($third->available_at->copy()->addSecond());
        app(NotificationDeliveryService::class)->deliver($third->id);
        $this->assertSame('canceled', $third->fresh()->status);
        $this->assertSame(1, Mail::mailer()->getSymfonyTransport()->messages()->count());
    }

    public function test_campaign_audience_requires_opt_in_and_cancel_stops_pending_deliveries(): void
    {
        config(['mail.default' => 'array']);
        $operator = User::factory()->create();
        $optedIn = User::factory()->create();
        $other = User::factory()->create();
        app(NotificationService::class)->updatePreferences($optedIn, ['marketing_email' => true]);
        app(NotificationService::class)->getPreferences($other);
        $campaign = MessagingCampaign::create(['uuid' => (string) Str::uuid(), 'created_by' => $operator->id, 'name' => 'An opt-in update',
            'channel' => 'email', 'type' => 'broadcast', 'body' => 'Update', 'subject' => 'News', 'status' => 'draft']);
        $service = app(CampaignDeliveryService::class);
        $this->assertSame(1, $service->launch($campaign));
        $this->assertSame(1, $service->launch($campaign));
        $this->assertSame($optedIn->id, NotificationDelivery::first()->user_id);
        $service->cancel($campaign);
        app(NotificationDeliveryService::class)->deliver(NotificationDelivery::first()->id);
        $this->assertSame('canceled', NotificationDelivery::first()->status);
        $this->assertCount(0, Mail::mailer()->getSymfonyTransport()->messages());
    }

    public function test_unknown_transport_acknowledgment_is_not_retried_and_internal_push_endpoints_are_rejected(): void
    {
        $user = User::factory()->create();
        app(NotificationService::class)->sendPaymentReceipt($user, 'ambiguous', 10, 'NGN');
        Mail::shouldReceive('html')->once()->andThrow(new \RuntimeException('Simulated transport timeout'));
        $row = NotificationDelivery::firstOrFail();
        $delivery = app(NotificationDeliveryService::class);
        $delivery->deliver($row->id);
        $delivery->deliver($row->id);
        $this->assertSame('uncertain', $row->fresh()->status);
        $this->actingAs($user)->postJson('/api/push/subscribe', ['endpoint' => 'https://127.0.0.1/internal', 'keys' => ['auth' => str_repeat('a', 22), 'p256dh' => str_repeat('b', 87)]])->assertUnprocessable();
    }
}
