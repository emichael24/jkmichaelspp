<?php

namespace App\Http\Controllers;

use App\Models\CrmContact;
use App\Services\CampaignAudienceService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ContactMessagePreferenceController extends Controller
{
    public function show(Request $request, CrmContact $contact, string $channel)
    {
        abort_unless(in_array($channel, ['mail', 'whatsapp'], true), 404);

        return response()->view('mail.unsubscribe', ['channel' => $channel, 'done' => false]);
    }

    public function update(Request $request, CrmContact $contact, string $channel)
    {
        abort_unless(in_array($channel, ['mail', 'whatsapp'], true), 404);
        DB::transaction(function () use ($contact, $channel) {
            $field = $channel === 'mail' ? 'marketing_email' : 'marketing_whatsapp';
            $contact->update([$field => false]);
            if ($user = app(CampaignAudienceService::class)->account($contact)) {
                app(NotificationService::class)->updatePreferences($user, [$field => false]);
            }
        });

        return response()->view('mail.unsubscribe', ['channel' => $channel, 'done' => true]);
    }
}
