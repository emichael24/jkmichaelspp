<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MessagingCampaign;
use App\Models\MessagingLog;
use App\Models\User;
use App\Models\CrmContact;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;

class MessagingController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    // ─── Messaging Hub ────────────────────────────────────────────────────────────
    public function index()
    {
        return Inertia::render('Admin/ERP/Messaging/Index', [
            'campaigns' => MessagingCampaign::with('createdBy')
                ->latest()
                ->paginate(20),
            'stats' => [
                'total_sent'      => MessagingLog::where('status', 'sent')->count(),
                'total_delivered' => MessagingLog::where('status', 'delivered')->count(),
                'total_failed'    => MessagingLog::where('status', 'failed')->count(),
                'email_sent'      => MessagingLog::where('channel', 'email')->where('status', 'sent')->count(),
                'wa_sent'         => MessagingLog::where('channel', 'whatsapp')->where('status', 'sent')->count(),
            ],
        ]);
    }

    // ─── Create Campaign ──────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'            => 'required|string|max:255',
            'channel'         => 'required|in:email,whatsapp',
            'type'            => 'required|in:broadcast,sequence,trigger',
            'subject'         => 'required_if:channel,email|nullable|string|max:255',
            'body'            => 'required|string',
            'media_url'       => 'nullable|url|max:500',
            'audience_filter' => 'nullable|array',
            'scheduled_at'    => 'nullable|date|after:now',
        ]);

        $campaign = MessagingCampaign::create([
            ...$validated,
            'uuid'       => (string) Str::uuid(),
            'created_by' => auth()->id(),
            'status'     => $request->filled('scheduled_at') ? 'scheduled' : 'draft',
        ]);

        return redirect()->route('admin.messaging.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    public function show(MessagingCampaign $campaign)
    {
        $campaign->load(['createdBy', 'logs' => fn ($q) => $q->limit(100)]);

        return Inertia::render('Admin/ERP/Messaging/Show', [
            'campaign' => $campaign,
        ]);
    }

    // ─── Send / Launch Campaign ───────────────────────────────────────────────────
    public function send(MessagingCampaign $campaign)
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'])) {
            return back()->with('error', 'Campaign cannot be sent in its current state.');
        }

        $recipients = $this->resolveAudience($campaign->audience_filter ?? []);
        $campaign->update([
            'status'           => 'sending',
            'recipient_count'  => count($recipients),
            'started_at'       => now(),
        ]);

        if ($campaign->channel === 'whatsapp') {
            $this->sendWhatsAppCampaign($campaign, $recipients);
        } else {
            $this->sendEmailCampaign($campaign, $recipients);
        }

        return back()->with('success', 'Campaign is being sent.');
    }

    // ─── Quick / Direct Send (one-off message) ────────────────────────────────────
    public function quickSend(Request $request)
    {
        $request->validate([
            'channel'      => 'required|in:email,whatsapp',
            'recipient'    => 'required|string',
            'subject'      => 'required_if:channel,email|nullable|string|max:255',
            'message'      => 'required|string|max:4096',
            'contact_id'   => 'nullable|exists:crm_contacts,id',
            'user_id'      => 'nullable|exists:users,id',
        ]);

        $recipient = $request->input('recipient');
        $channel   = $request->input('channel');
        $message   = $request->input('message');

        if ($channel === 'whatsapp') {
            $result = $this->whatsapp->sendText($recipient, $message);
            $externalId = $result['messageId'] ?? null;
        } else {
            // Send via Laravel Mail
            Mail::raw($message, function ($m) use ($recipient, $request) {
                $m->to($recipient)->subject($request->input('subject', 'Message from JK Michaels'));
            });
            $externalId = null;
        }

        MessagingLog::create([
            'campaign_id'         => null,
            'user_id'             => $request->input('user_id'),
            'contact_id'          => $request->input('contact_id'),
            'channel'             => $channel,
            'recipient'           => $recipient,
            'subject'             => $request->input('subject'),
            'body'                => $message,
            'external_message_id' => $externalId,
            'status'              => 'sent',
            'sent_at'             => now(),
        ]);

        return response()->json(['success' => true, 'external_id' => $externalId]);
    }

    // ─── WhatsApp Session Management ─────────────────────────────────────────────
    public function waSessionsIndex()
    {
        $sessions = \App\Models\WhatsappSession::all();
        $gatewayStatus = null;

        try {
            $gatewayStatus = $this->whatsapp->gatewayStatus();
        } catch (\Throwable) {}

        return Inertia::render('Admin/ERP/WhatsApp/Sessions', [
            'sessions'      => $sessions,
            'gatewayStatus' => $gatewayStatus,
        ]);
    }

    public function createWaSession(Request $request)
    {
        $request->validate([
            'session_name' => 'required|string|max:100',
        ]);

        $webhookUrl = route('webhooks.whatsapp');
        $result = $this->whatsapp->createSession($request->input('session_name'), $webhookUrl);

        \App\Models\WhatsappSession::updateOrCreate(
            ['session_id' => $result['id']],
            [
                'name'        => $request->input('session_name'),
                'status'      => $result['status'] ?? 'WAITING_FOR_QR',
                'webhook_url' => $webhookUrl,
            ]
        );

        return response()->json(['session' => $result]);
    }

    public function getWaQrCode(string $sessionId)
    {
        $qr = $this->whatsapp->getQrCode($sessionId);
        return response()->json($qr);
    }

    public function waSessionStatus(string $sessionId)
    {
        $status = $this->whatsapp->getSession($sessionId);

        // Update local record
        \App\Models\WhatsappSession::where('session_id', $sessionId)->update([
            'status'       => $status['status'] ?? 'DISCONNECTED',
            'phone'        => $status['phone'] ?? null,
            'connected_at' => isset($status['connectedAt']) ? \Carbon\Carbon::parse($status['connectedAt']) : null,
        ]);

        return response()->json($status);
    }

    public function logoutWaSession(string $sessionId)
    {
        $result = $this->whatsapp->logoutSession($sessionId);

        \App\Models\WhatsappSession::where('session_id', $sessionId)->update([
            'status' => 'DISCONNECTED',
            'phone'  => null,
        ]);

        return response()->json($result);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────────

    protected function resolveAudience(array $filter): array
    {
        $query = User::query();

        if (! empty($filter['role'])) {
            $query->role($filter['role']);
        }

        if (! empty($filter['course_id'])) {
            $query->whereHas('enrollments', fn ($q) => $q->where('course_id', $filter['course_id']));
        }

        if (! empty($filter['cohort_id'])) {
            $query->whereHas('cohortMemberships', fn ($q) => $q->where('cohort_id', $filter['cohort_id']));
        }

        return $query->get(['id', 'email', 'phone', 'first_name', 'last_name'])->toArray();
    }

    protected function sendWhatsAppCampaign(MessagingCampaign $campaign, array $recipients): void
    {
        $chunks = array_chunk($recipients, 500);
        $sent = 0;
        $failed = 0;

        foreach ($chunks as $chunk) {
            $waRecipients = array_filter(array_map(fn ($r) => [
                'number'  => $r['phone'] ?? null,
                'message' => $campaign->body,
            ], $chunk), fn ($r) => ! empty($r['number']));

            if (empty($waRecipients)) continue;

            try {
                $result = $this->whatsapp->sendBulk(array_values($waRecipients));
                $sent   += $result['sent'] ?? count($waRecipients);
                $failed += $result['failed'] ?? 0;

                foreach ($result['details'] ?? [] as $detail) {
                    MessagingLog::create([
                        'campaign_id'         => $campaign->id,
                        'channel'             => 'whatsapp',
                        'recipient'           => $detail['number'],
                        'body'                => $campaign->body,
                        'external_message_id' => $detail['messageId'] ?? null,
                        'status'              => $detail['status'] === 'queued' ? 'sent' : 'failed',
                        'sent_at'             => now(),
                    ]);
                }
            } catch (\Throwable $e) {
                $failed += count($waRecipients);
            }
        }

        $campaign->update([
            'status'       => 'sent',
            'sent_count'   => $sent,
            'failed_count' => $failed,
            'completed_at' => now(),
        ]);
    }

    protected function sendEmailCampaign(MessagingCampaign $campaign, array $recipients): void
    {
        $sent = 0;
        $failed = 0;

        foreach ($recipients as $recipient) {
            if (empty($recipient['email'])) { $failed++; continue; }

            try {
                Mail::html($campaign->body, function ($m) use ($recipient, $campaign) {
                    $m->to($recipient['email'], $recipient['first_name'] . ' ' . $recipient['last_name'])
                      ->subject($campaign->subject ?? 'Message from JK Michaels');
                });

                MessagingLog::create([
                    'campaign_id' => $campaign->id,
                    'user_id'     => $recipient['id'],
                    'channel'     => 'email',
                    'recipient'   => $recipient['email'],
                    'subject'     => $campaign->subject,
                    'body'        => $campaign->body,
                    'status'      => 'sent',
                    'sent_at'     => now(),
                ]);

                $sent++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        $campaign->update([
            'status'       => 'sent',
            'sent_count'   => $sent,
            'failed_count' => $failed,
            'completed_at' => now(),
        ]);
    }
}
