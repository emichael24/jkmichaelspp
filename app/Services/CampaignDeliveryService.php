<?php

namespace App\Services;

use App\Models\CrmContact;
use App\Models\MessagingCampaign;
use App\Models\MessagingLog;
use App\Models\NotificationDelivery;
use Illuminate\Support\Facades\DB;

class CampaignDeliveryService
{
    public function launch(MessagingCampaign $campaign): int
    {
        return DB::transaction(function () use ($campaign) {
            $campaign = MessagingCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            if ($campaign->status === 'sending') {
                return $campaign->recipient_count;
            }
            abort_unless(in_array($campaign->status, ['draft', 'scheduled'], true), 409, 'This campaign cannot be launched.');
            abort_unless($campaign->type === 'broadcast' && ! $campaign->media_url, 422, 'Only broadcasts without media attachments are available.');
            $channel = $campaign->channel === 'email' ? 'mail' : $campaign->channel;
            abort_unless(app(NotificationDeliveryService::class)->configured($channel), 422, 'Configure and verify this delivery provider first.');
            $filter = $campaign->audience_filter ?? [];
            $stats = app(CampaignAudienceService::class)->each($filter, $channel, function ($person, $recipient, $key) use ($campaign, $channel) {
                $contact = $person instanceof CrmContact ? $person : null;
                $user = $contact ? app(CampaignAudienceService::class)->account($contact) : $person;
                $log = MessagingLog::create(['campaign_id' => $campaign->id, 'user_id' => $user?->id, 'contact_id' => $contact?->id,
                    'channel' => $campaign->channel, 'recipient' => $recipient, 'subject' => $campaign->subject,
                    'body' => $campaign->body, 'status' => 'queued']);
                $available = $user ? app(NotificationDeliveryService::class)->availableAt($user, app(NotificationService::class)->getPreferences($user)) : now();
                if ($campaign->scheduled_at?->greaterThan($available)) {
                    $available = $campaign->scheduled_at;
                }
                NotificationDelivery::create(['event_key' => hash('sha256', 'campaign:'.$campaign->id.':'.$key),
                    'user_id' => $user?->id, 'contact_id' => $contact?->id, 'campaign_id' => $campaign->id, 'messaging_log_id' => $log->id,
                    'channel' => $channel, 'category' => 'marketing',
                    'payload' => ['title' => $campaign->subject ?: $campaign->name, 'body' => $campaign->body, 'body_format' => $campaign->body_format ?? 'text', 'recipient_type' => $contact ? 'contact' : 'user'],
                    'available_at' => $available]);
            });
            $count = $stats['eligible'];
            abort_if($count === 0, 422, 'No eligible recipients match this audience. Review channel preferences and contact details.');
            $campaign->update(['status' => 'sending', 'recipient_count' => $count, 'started_at' => now()]);

            return $count;
        });
    }

    public function cancel(MessagingCampaign $campaign): void
    {
        DB::transaction(function () use ($campaign) {
            $campaign = MessagingCampaign::whereKey($campaign->id)->lockForUpdate()->firstOrFail();
            abort_if(in_array($campaign->status, ['sent', 'cancelled'], true), 409, 'This campaign has finished.');
            $campaign->update(['status' => 'cancelled']);
            NotificationDelivery::where('campaign_id', $campaign->id)->where('status', 'pending')->update(['status' => 'canceled', 'error_code' => 'campaign_canceled']);
            MessagingLog::where('campaign_id', $campaign->id)->where('status', 'queued')->update(['status' => 'failed', 'error_message' => 'campaign_canceled']);
        });
    }

    public function reconcile(int $id): void
    {
        DB::transaction(function () use ($id) {
            $campaign = MessagingCampaign::whereKey($id)->lockForUpdate()->first();
            if (! $campaign) {
                return;
            }
            $counts = NotificationDelivery::where('campaign_id', $id)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
            $campaign->sent_count = $counts['sent'] ?? 0;
            $campaign->failed_count = ($counts['failed'] ?? 0) + ($counts['uncertain'] ?? 0) + ($counts['canceled'] ?? 0);
            if (! ($counts['pending'] ?? 0) && ! ($counts['sending'] ?? 0)) {
                $campaign->status = $campaign->status === 'cancelled' ? 'cancelled' : (($counts['uncertain'] ?? 0) ? 'paused' : 'sent');
                $campaign->completed_at = now();
            }
            $campaign->save();
        });
    }
}
