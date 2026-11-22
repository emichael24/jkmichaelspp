<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ExternalIntegrationException;
use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmOrganization;
use App\Models\MessagingCampaign;
use App\Models\MessagingLog;
use App\Models\NotificationDelivery;
use App\Models\Organization;
use App\Models\User;
use App\Models\WhatsappSession;
use App\Services\CampaignAudienceService;
use App\Services\CampaignDeliveryService;
use App\Services\CrmContactService;
use App\Services\EmailContent;
use App\Services\NotificationDeliveryService;
use App\Services\NotificationService;
use App\Services\WhatsAppService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class MessagingController extends Controller
{
    public function __construct(protected WhatsAppService $whatsapp) {}

    // ─── Messaging Hub ────────────────────────────────────────────────────────────
    public function index(Request $request)
    {
        $filters = $request->validate(['search' => 'nullable|string|max:200', 'channel' => 'nullable|in:email,whatsapp,inbox']);

        return Inertia::render('Admin/ERP/Messaging/Index', [
            'filters' => $filters,
            'audienceOptions' => [
                'roles' => Role::where('guard_name', 'web')->orderBy('name')->pluck('name'),
                'organizations' => Organization::where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'crmOrganizations' => CrmOrganization::orderBy('name')->get(['id', 'name']),
                'contactTypes' => CrmContactService::TYPES,
            ],
            'initialAudience' => [
                'source' => in_array($request->query('audience'), ['users', 'crm', 'all'], true) ? $request->query('audience') : 'users',
                'contact_types' => in_array($request->query('contact_type'), CrmContactService::TYPES, true) ? [$request->query('contact_type')] : [],
            ],
            'compose' => $request->boolean('compose'),
            'campaigns' => MessagingCampaign::with('createdBy')
                ->when($filters['search'] ?? null, fn ($q, $search) => $q->where('name', 'like', '%'.$search.'%'))
                ->when($filters['channel'] ?? null, fn ($q, $channel) => $q->where('channel', $channel))
                ->latest()
                ->paginate(20)->withQueryString(),
            'stats' => [
                'total_sent' => MessagingLog::where('status', 'sent')->count(),
                'total_delivered' => MessagingLog::where('status', 'delivered')->count(),
                'total_failed' => MessagingLog::where('status', 'failed')->count(),
                'email_sent' => MessagingLog::where('channel', 'email')->where('status', 'sent')->count(),
                'wa_sent' => MessagingLog::where('channel', 'whatsapp')->where('status', 'sent')->count(),
            ],
        ]);
    }

    // ─── Create Campaign ──────────────────────────────────────────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'channel' => 'required|in:email,whatsapp,inbox',
            'type' => 'required|in:broadcast',
            'subject' => 'required_if:channel,email,inbox|nullable|string|max:255',
            'body' => 'required|string|max:'.($request->input('channel') === 'email' ? 100000 : 3500),
            'body_format' => ['sometimes', Rule::in($request->input('channel') === 'email' ? ['text', 'html'] : ['text'])],
            'media_url' => 'prohibited',
            ...CampaignAudienceService::rules(),
            'scheduled_at' => 'nullable|date|after:now',
            'send_immediately' => 'boolean',
        ]);

        $validated['body'] = app(EmailContent::class)->prepare($validated['body'], $validated['body_format'] ?? 'text');
        $campaign = DB::transaction(function () use ($validated, $request) {
            $campaign = MessagingCampaign::create([
                ...$validated,
                'uuid' => (string) Str::uuid(),
                'created_by' => auth()->id(),
                'status' => $request->filled('scheduled_at') ? 'scheduled' : 'draft',
            ]);

            if ($request->boolean('send_immediately') && ! $campaign->scheduled_at) {
                app(CampaignDeliveryService::class)->launch($campaign);
            }

            return $campaign;
        });

        return redirect()->route('admin.messaging.show', $campaign)
            ->with('success', 'Campaign created.');
    }

    public function show(MessagingCampaign $campaign)
    {
        $campaign->load(['createdBy', 'logs' => fn ($q) => $q->limit(100)]);

        return Inertia::render('Admin/ERP/Messaging/Show', [
            'campaign' => $campaign,
            'audiencePreview' => in_array($campaign->status, ['draft', 'scheduled'], true) ? app(CampaignAudienceService::class)->each($campaign->audience_filter ?? [], $campaign->channel === 'email' ? 'mail' : $campaign->channel) : null,
            'deliveries' => NotificationDelivery::where('campaign_id', $campaign->id)->latest()->limit(100)->get(['id', 'user_id', 'contact_id', 'messaging_log_id', 'status', 'error_code', 'attempts', 'sent_at']),
        ]);
    }

    // ─── Send / Launch Campaign ───────────────────────────────────────────────────
    public function send(MessagingCampaign $campaign)
    {
        $count = app(CampaignDeliveryService::class)->launch($campaign);

        return back()->with('success', "Campaign queued for {$count} eligible recipients. Check the delivery log for results.");
    }

    public function cancel(MessagingCampaign $campaign)
    {
        app(CampaignDeliveryService::class)->cancel($campaign);

        return back()->with('success', 'Pending messages canceled. Messages already being sent may still arrive.');
    }

    public function audiencePreview(Request $request)
    {
        $data = $request->validate(['channel' => 'required|in:email,whatsapp,inbox', ...CampaignAudienceService::rules()]);

        return response()->json(app(CampaignAudienceService::class)->each($data['audience_filter'] ?? [], $data['channel'] === 'email' ? 'mail' : $data['channel']))
            ->header('Cache-Control', 'private, no-store');
    }

    // Individual CRM correspondence is explicitly addressed; campaign consent is separate.
    public function quickSend(Request $request)
    {
        $data = $request->validate([
            'channel' => 'required|in:email,whatsapp',
            'user_id' => 'nullable|required_without:recipient_id|integer|exists:users,id|prohibits:recipient_id',
            'recipient_type' => 'required_with:recipient_id|in:contact',
            'recipient_id' => 'nullable|required_if:recipient_type,contact|integer|exists:crm_contacts,id',
            'subject' => 'required_if:channel,email|nullable|string|max:255',
            'message' => 'required|string|max:'.($request->input('channel') === 'email' ? 100000 : 3500),
            'body_format' => ['sometimes', Rule::in($request->input('channel') === 'email' ? ['text', 'html'] : ['text'])],
        ]);
        $contact = isset($data['recipient_id']) ? CrmContact::findOrFail($data['recipient_id']) : null;
        $audience = app(CampaignAudienceService::class);
        $user = $contact ? $audience->account($contact) : User::findOrFail($data['user_id']);
        $person = $contact ?? $user;
        $channel = $data['channel'] === 'email' ? 'mail' : 'whatsapp';
        if (! $audience->eligible($person, $channel, ! $contact)) {
            throw ValidationException::withMessages(['recipient_id' => 'This recipient is inactive, has invalid contact details, or has not enabled this channel.']);
        }
        if (! app(NotificationDeliveryService::class)->configured($channel)) {
            throw ValidationException::withMessages(['channel' => 'Configure the delivery provider first.']);
        }
        $data['message'] = app(EmailContent::class)->prepare($data['message'], $data['body_format'] ?? 'text', 'message');
        $log = DB::transaction(function () use ($data, $user, $contact, $person, $audience, $channel) {
            $log = MessagingLog::create(['user_id' => $user?->id, 'contact_id' => $contact?->id, 'channel' => $data['channel'],
                'recipient' => $audience->address($person, $channel), 'subject' => $data['subject'] ?? null, 'body' => $data['message'], 'status' => 'queued']);
            NotificationDelivery::create(['event_key' => hash('sha256', 'direct:'.$log->id),
                'user_id' => $user?->id, 'contact_id' => $contact?->id, 'messaging_log_id' => $log->id, 'channel' => $channel, 'category' => $contact ? 'correspondence' : 'marketing',
                'payload' => ['title' => $data['subject'] ?? 'JK Michaels', 'body' => $data['message'], 'body_format' => $data['body_format'] ?? 'text', 'recipient_type' => $contact ? 'contact' : 'user'],
                'available_at' => $user ? app(NotificationDeliveryService::class)->availableAt($user, app(NotificationService::class)->getPreferences($user)) : now()]);
            if ($contact) {
                CrmActivity::create(['contact_id' => $contact->id, 'user_id' => auth()->id(), 'type' => $data['channel'],
                    'subject' => $data['subject'] ?? 'WhatsApp correspondence', 'description' => 'Queued for delivery. Message log #'.$log->id, 'direction' => 'outbound']);
            }

            return $log;
        });
        if ($request->header('X-Inertia')) {
            return back()->with('success', 'Message queued for delivery.');
        }

        return response()->json(['queued' => true, 'delivery_id' => $log->id], 202);
    }

    // ─── WhatsApp Session Management ─────────────────────────────────────────────
    public function waSessionsIndex()
    {
        $sessions = WhatsappSession::all();
        $gatewayStatus = null;
        $gatewayError = null;

        try {
            $gatewayStatus = $this->whatsapp->gatewayStatus();
        } catch (ExternalIntegrationException $e) {
            $gatewayError = $e->getMessage();
        }

        return Inertia::render('Admin/ERP/WhatsApp/Sessions', [
            'sessions' => $sessions,
            'gatewayStatus' => $gatewayStatus,
            'gatewayError' => $gatewayError,
        ]);
    }

    public function createWaSession(Request $request)
    {
        $request->validate([
            'session_name' => 'required|string|max:100',
        ]);

        // Use an application-owned, unpredictable gateway identifier. Display labels
        // must never collide with sessions belonging to another gateway customer.
        $result = $this->whatsapp->createSession('jkm-'.Str::uuid());

        WhatsappSession::updateOrCreate(
            ['session_id' => $result['id']],
            [
                'name' => $request->input('session_name'),
                'status' => $result['status'] ?? 'WAITING_FOR_QR',
                'webhook_url' => null,
            ]
        );

        return response()->json(['session' => $result]);
    }

    public function getWaQrCode(string $sessionId)
    {
        WhatsappSession::where('session_id', $sessionId)->firstOrFail();
        $qr = $this->whatsapp->getQrCode($sessionId);

        return response()->json($qr);
    }

    public function waSessionStatus(string $sessionId)
    {
        WhatsappSession::where('session_id', $sessionId)->firstOrFail();
        $status = $this->whatsapp->getSession($sessionId);

        // Update local record
        WhatsappSession::where('session_id', $sessionId)->update([
            'status' => $status['status'],
            'phone' => $status['phone'] ?? null,
            'connected_at' => isset($status['connectedAt']) ? Carbon::parse($status['connectedAt']) : null,
        ]);

        return response()->json($status);
    }

    public function logoutWaSession(string $sessionId)
    {
        WhatsappSession::where('session_id', $sessionId)->firstOrFail();
        $result = $this->whatsapp->logoutSession($sessionId);

        WhatsappSession::where('session_id', $sessionId)->update([
            'status' => 'DISCONNECTED',
            'phone' => null,
            'connected_at' => null,
        ]);

        return response()->json($result);
    }
}
