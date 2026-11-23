<?php

namespace App\Services;

use App\Jobs\DeliverNotification;
use App\Models\CrmContact;
use App\Models\DirectMessage;
use App\Models\MessagingCampaign;
use App\Models\MessagingLog;
use App\Models\NotificationDelivery;
use App\Models\NotificationPreference;
use App\Models\PushSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class NotificationDeliveryService
{
    public function availableAt(User $user, NotificationPreference $prefs, bool $digest = false): Carbon
    {
        $time = now()->setTimezone($user->timezone ?: 'UTC');
        if ($digest && $prefs->frequency !== 'realtime') {
            $time = $prefs->frequency === 'weekly_digest' ? $time->next('Monday')->setTime(9, 0) : $time->addDay()->setTime(9, 0);
        }
        $start = substr($prefs->quiet_hours_start ?? '', 0, 5);
        $end = substr($prefs->quiet_hours_end ?? '', 0, 5);
        $clock = $time->format('H:i');
        if ($start && $end && $start !== $end && ($start < $end ? $clock >= $start && $clock < $end : $clock >= $start || $clock < $end)) {
            if ($start > $end && $clock >= $start) {
                $time->addDay();
            }
            [$hour, $minute] = explode(':', $end);
            $time->setTime((int) $hour, (int) $minute);
        }

        return $time->utc();
    }

    public function enqueue(User $user, string $category, array $data, string $eventKey): void
    {
        $prefs = app(NotificationService::class)->getPreferences($user);
        if ($prefs->shouldSendEmail($category)) {
            $available = $this->availableAt($user, $prefs, true);
            NotificationDelivery::firstOrCreate(['event_key' => hash('sha256', $eventKey.':mail')], [
                'user_id' => $user->id, 'channel' => 'mail', 'category' => $category, 'payload' => $data,
                'available_at' => $available, 'group_key' => $prefs->frequency === 'realtime' ? null : hash('sha256', $user->id.':mail:'.$available->toIso8601String()),
            ]);
        }
        if ($prefs->push_enabled && $prefs->isCategoryEnabled($category)) {
            foreach (PushSubscription::where('user_id', $user->id)->get() as $subscription) {
                NotificationDelivery::firstOrCreate(['event_key' => hash('sha256', $eventKey.':push:'.$subscription->id)], [
                    'user_id' => $user->id, 'push_subscription_id' => $subscription->id, 'channel' => 'push',
                    'category' => $category, 'payload' => $data, 'available_at' => $this->availableAt($user, $prefs),
                ]);
            }
        }
    }

    public function dispatchDue(): int
    {
        // A killed worker may have handed a message to SMTP. Do not resend an ambiguous delivery automatically.
        NotificationDelivery::where('status', 'sending')->where('started_at', '<', now()->subMinutes(10))
            ->update(['status' => 'uncertain', 'error_code' => 'worker_interrupted']);
        $ids = NotificationDelivery::where('status', 'pending')->where('available_at', '<=', now())->orderBy('available_at')->limit(500)->pluck('id');
        foreach ($ids as $id) {
            DeliverNotification::dispatch($id);
        }

        return $ids->count();
    }

    public function deliver(string $id): void
    {
        $candidate = NotificationDelivery::find($id);
        if (! $candidate) {
            return;
        }
        $batch = DB::transaction(function () use ($candidate) {
            $user = User::whereKey($candidate->user_id)->lockForUpdate()->first();
            $first = NotificationDelivery::whereKey($candidate->id)->lockForUpdate()->first();
            if (! $first || $first->status !== 'pending' || $first->available_at->isFuture()) {
                return collect();
            }
            $rows = $first->group_key ? NotificationDelivery::where('user_id', $first->user_id)->where('group_key', $first->group_key)->where('status', 'pending')->where('available_at', '<=', now())->orderBy('id')->limit(100)->lockForUpdate()->get() : collect([$first]);
            $prefs = $user ? app(NotificationService::class)->getPreferences($user) : null;
            $contact = $first->contact_id ? CrmContact::whereKey($first->contact_id)->lockForUpdate()->first() : null;
            $ready = collect();
            foreach ($rows as $row) {
                if (! $this->recipientAllowed($row, $user, $contact, $prefs)) {
                    $this->cancelRow($row, 'recipient_preference_or_access');

                    continue;
                }
                $available = $user ? $this->availableAt($user, $prefs) : now();
                if ($available->isFuture()) {
                    $row->update(['available_at' => $available]);

                    continue;
                }
                $limit = NotificationDelivery::query()->where($user ? 'user_id' : 'contact_id', $user?->id ?? $contact?->id)->where('channel', $row->channel)->where('status', 'sent')->where('sent_at', '>=', now()->subDay())->count();
                $maximum = $row->channel === 'push' ? app(PlatformSettings::class)->get('notifications.max_push_per_day', 3) : config('notifications.daily_message_limit');
                if ($row->category !== 'system' && $limit >= $maximum) {
                    $row->update(['available_at' => now()->addDay(), 'error_code' => 'frequency_limit']);

                    continue;
                }
                $enabled = app(PlatformSettings::class)->get('notifications.'.($row->channel === 'mail' ? 'email_enabled' : 'push_enabled'), true);
                if (in_array($row->channel, ['mail', 'push'], true) && ! $enabled) {
                    $this->cancelRow($row, 'platform_channel_disabled');

                    continue;
                }
                if (! $this->configured($row->channel)) {
                    $row->update(['available_at' => now()->addMinutes(5), 'error_code' => 'provider_not_configured']);

                    continue;
                }
                $row->update(['status' => 'sending', 'started_at' => now(), 'attempts' => $row->attempts + 1, 'error_code' => null]);
                $ready->push($row);
            }

            return $ready;
        });
        if ($batch->isEmpty()) {
            if ($candidate->campaign_id) {
                app(CampaignDeliveryService::class)->reconcile($candidate->campaign_id);
            }

            return;
        }
        $first = $batch->first();
        $user = $first->user;
        try {
            $providerId = null;
            if ($first->channel === 'mail') {
                [$firstName, $fullName] = $this->recipientName($first);
                $payloads = $batch->map(fn ($row) => [...$row->payload,
                    'title' => EmailBranding::personalize((string) ($row->payload['title'] ?? 'JK Michaels'), $firstName, $fullName),
                    'body' => EmailBranding::personalize((string) ($row->payload['body'] ?? ''), $firstName, $fullName, ($row->payload['body_format'] ?? 'text') === 'html'),
                ]);
                $body = $payloads->map(fn ($payload) => $this->plainMessage($payload, $this->preferencesUrl($first)))->implode("\n\n———\n\n");
                $branding = app(EmailBranding::class);
                $subject = $batch->count() > 1 ? 'Your '.$branding->get()['brand_name'].' updates' : $payloads->first()['title'];
                $recipient = $first->messaging_log_id ? MessagingLog::findOrFail($first->messaging_log_id)->recipient : $user->email;
                $configure = function ($message) use ($recipient, $first, $subject, $body) {
                    $message->to($recipient)->subject($subject);
                    $message->getSymfonyMessage()->text($body);
                    $message->getSymfonyMessage()->getHeaders()->addIdHeader('Message-ID', $first->id.'@'.parse_url(config('app.url'), PHP_URL_HOST));
                };
                // Every email uses the branded layout; a plain-text alternative is always included.
                Mail::html($branding->render($this->htmlContent($payloads), $this->preferencesUrl($first)), $configure);
            } elseif ($first->channel === 'inbox') {
                $campaign = MessagingCampaign::find($first->campaign_id);
                $sender = $campaign?->createdBy;
                if (! $user || ! $sender?->is_active || ! $sender->hasRole('platform_admin')) {
                    $this->finish($batch, 'canceled', 'sender_or_recipient_unavailable');

                    return;
                }
                DB::transaction(function () use ($first, $sender, $user) {
                    DirectMessage::firstOrCreate(['delivery_id' => $first->id], ['sender_id' => $sender->id,
                        'recipient_id' => $user->id, 'campaign_id' => $first->campaign_id,
                        'body' => ($first->payload['title'] ?? 'JK Michaels')."\n\n".($first->payload['body'] ?? ''), 'created_at' => now()]);
                    $this->finish(collect([$first]), 'sent');
                });

                return;
            } elseif ($first->channel === 'push') {
                $subscription = PushSubscription::whereKey($first->push_subscription_id)->where('user_id', $user->id)->first();
                if (! $subscription) {
                    $this->finish($batch, 'canceled', 'subscription_removed');

                    return;
                }
                $this->assertPushEndpoint($subscription->subscription['endpoint']);
                $push = new WebPush(['VAPID' => config('notifications.vapid')], ['TTL' => 3600], 20, ['allow_redirects' => false]);
                // Notification previews never disclose private learning, grading or billing content on a locked device.
                $report = $push->sendOneNotification(Subscription::create($subscription->subscription), json_encode(['title' => 'JK Michaels', 'body' => 'You have a new update.', 'url' => '/notifications', 'tag' => $first->id]));
                if ($report->isSubscriptionExpired()) {
                    $subscription->delete();
                    $this->finish($batch, 'canceled', 'subscription_expired');

                    return;
                }
                if (! $report->isSuccess()) {
                    $status = $report->getResponse()?->getStatusCode();
                    if (in_array($status, [429, 500, 502, 503, 504], true) && $first->attempts < 5) {
                        $first->update(['status' => 'pending', 'available_at' => now()->addMinutes(2 ** $first->attempts), 'error_code' => 'provider_retry']);

                        return;
                    }
                    $this->finish($batch, $status ? 'failed' : 'uncertain', 'push_not_accepted');

                    return;
                }
            } elseif ($first->channel === 'whatsapp') {
                $recipient = $first->messaging_log_id ? MessagingLog::findOrFail($first->messaging_log_id)->recipient : ($user->whatsapp_number ?: $user->phone);
                $result = app(WhatsAppService::class)->sendText($recipient, $this->plainMessage($first->payload, $this->preferencesUrl($first)));
                $providerId = $result['messageId'] ?? null;
                if (! $providerId) {
                    $this->finish($batch, 'uncertain', 'missing_provider_acknowledgment');

                    return;
                }
            }
            $this->finish($batch, 'sent', null, $providerId);
        } catch (\Throwable $error) {
            // SMTP and network timeouts can happen after acceptance; retain uncertainty instead of claiming failure or retrying a possible duplicate.
            $this->finish($batch, 'uncertain', 'transport_acknowledgment_unknown');
            report(new \RuntimeException('Notification delivery '.$first->id.' needs reconciliation ('.class_basename($error).').'));
        }
    }

    private function recipientAllowed(NotificationDelivery $row, ?User $user, ?CrmContact $contact, ?NotificationPreference $prefs): bool
    {
        if ($row->category === 'marketing' && $row->campaign_id && MessagingCampaign::find($row->campaign_id)?->status !== 'sending') {
            return false;
        }
        if ($row->messaging_log_id) {
            $log = MessagingLog::find($row->messaging_log_id);
            $isCrm = $row->contact_id || ($row->payload['recipient_type'] ?? null) === 'contact';
            $person = $isCrm ? $contact : $user;
            $audience = app(CampaignAudienceService::class);
            if (! $person || ! $log || ! $audience->eligible($person, $row->channel, $row->category === 'marketing')) {
                return false;
            }
            if ($contact && $audience->account($contact)?->id !== $row->user_id) {
                return false;
            }

            return $audience->address($person, $row->channel) === $log->recipient;
        }

        return $user && $user->is_active && (! $user->organization_id || $user->organization?->is_active)
            && $prefs && $prefs->isCategoryEnabled($row->category) && ($row->channel === 'mail' ? $prefs->email_enabled : $prefs->push_enabled);
    }

    private function preferencesUrl(NotificationDelivery $row): string
    {
        if ($row->contact_id) {
            return URL::signedRoute('messaging.unsubscribe', ['contact' => $row->contact_id, 'channel' => $row->channel]);
        }

        return rtrim(config('app.url'), '/').'/notifications/preferences';
    }

    public function configured(string $channel): bool
    {
        return match ($channel) {
            'mail' => app()->environment('testing') || ! in_array(config('mail.default'), ['log', 'array', 'failover', null], true),
            'push' => (bool) config('notifications.vapid.publicKey') && (bool) config('notifications.vapid.privateKey'),
            'whatsapp' => (bool) config('services.flexichurch.api_key'), 'inbox' => true, default => false,
        };
    }

    public function assertPushEndpoint(string $endpoint): void
    {
        $url = parse_url($endpoint);
        $host = strtolower($url['host'] ?? '');
        $allowed = collect(config('notifications.push_hosts'))->contains(fn ($pattern) => str_starts_with($pattern, '*.') ? str_ends_with($host, substr($pattern, 1)) : $host === $pattern);
        abort_unless(($url['scheme'] ?? '') === 'https' && ! isset($url['user']) && ! isset($url['pass']) && ! isset($url['fragment']) && ($url['port'] ?? 443) === 443 && $allowed, 422, 'Unsupported push endpoint.');
    }

    /** @return array{0: ?string, 1: ?string} first and full name of the recipient, for merge tags */
    private function recipientName(NotificationDelivery $row): array
    {
        $person = $row->contact_id ? CrmContact::find($row->contact_id) : $row->user;
        if (! $person) {
            return [null, null];
        }
        $first = trim((string) ($person->first_name ?? ''));
        $full = trim($first.' '.trim((string) ($person->last_name ?? '')));

        return [$first ?: null, $full ?: ($person->name ?? null)];
    }

    /** Branded HTML for one message or a digest of several (titles become headings). */
    private function htmlContent(Collection $payloads): string
    {
        $content = app(EmailContent::class);
        $sections = $payloads->map(function (array $payload) use ($content, $payloads) {
            $html = ($payload['body_format'] ?? 'text') === 'html'
                ? $content->sanitize((string) ($payload['body'] ?? ''))
                : collect(preg_split("/\R{2,}/", trim(html_entity_decode(strip_tags((string) ($payload['body'] ?? '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))))
                    ->filter(fn ($paragraph) => trim($paragraph) !== '')
                    ->map(fn ($paragraph) => '<p>'.nl2br(e(trim($paragraph)), false).'</p>')->implode('');
            $path = $payload['action_url'] ?? null;
            if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
                $html .= '<p><a href="'.e(rtrim(config('app.url'), '/').$path).'">Open in '.e(app(EmailBranding::class)->get()['brand_name']).'</a></p>';
            }
            $heading = $payloads->count() > 1 || ($payload['body_format'] ?? 'text') !== 'html' ? '<h3>'.e($payload['title'] ?? '').'</h3>' : '';

            return $heading.$html;
        });

        return $sections->implode('<hr>');
    }

    private function plainMessage(array $data, ?string $preferencesUrl = null): string
    {
        $body = ($data['title'] ?? 'JK Michaels')."\n\n".(($data['body_format'] ?? 'text') === 'html' ? app(EmailContent::class)->text($data['body'] ?? '') : html_entity_decode(strip_tags($data['body'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $path = $data['action_url'] ?? null;
        if (is_string($path) && str_starts_with($path, '/') && ! str_starts_with($path, '//') && ! str_contains($path, '\\')) {
            $body .= "\n\n".rtrim(config('app.url'), '/').$path;
        }

        return $body."\n\nManage your preferences: ".($preferencesUrl ?? rtrim(config('app.url'), '/').'/notifications/preferences');
    }

    private function cancelRow(NotificationDelivery $row, string $reason): void
    {
        $row->update(['status' => 'canceled', 'error_code' => $reason]);
        if ($row->messaging_log_id) {
            MessagingLog::find($row->messaging_log_id)?->update(['status' => 'failed', 'error_message' => $reason]);
        }
    }

    private function finish(Collection $rows, string $status, ?string $error = null, ?string $providerId = null): void
    {
        foreach ($rows as $row) {
            if ($status === 'sent' && $row->contact_id) {
                CrmContact::find($row->contact_id)?->update(['last_contacted_at' => now()]);
            }
            $row->update(['status' => $status, 'error_code' => $error, 'provider_id' => $providerId, 'sent_at' => $status === 'sent' ? now() : null]);
            if ($row->messaging_log_id) {
                MessagingLog::find($row->messaging_log_id)?->update(['status' => $status === 'sent' ? 'sent' : 'failed', 'error_message' => $error, 'external_message_id' => $providerId, 'sent_at' => $row->sent_at]);
            }
        }
        foreach ($rows->pluck('campaign_id')->filter()->unique() as $campaignId) {
            app(CampaignDeliveryService::class)->reconcile($campaignId);
        }
    }
}
