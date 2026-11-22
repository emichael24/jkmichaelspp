<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use App\Models\User;
use App\Services\NotificationDeliveryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PushSubscriptionController extends Controller
{
    public function key(NotificationDeliveryService $delivery)
    {
        abort_unless($delivery->configured('push'), 503, 'Push notifications are not configured yet.');

        return response()->json(['public_key' => config('notifications.vapid.publicKey')]);
    }

    public function store(Request $request, NotificationDeliveryService $delivery)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2000', 'keys' => 'required|array:p256dh,auth',
            'keys.p256dh' => 'required|string|max:120', 'keys.auth' => 'required|string|max:32']);
        $delivery->assertPushEndpoint($data['endpoint']);
        $decode = fn ($value) => base64_decode(strtr($value, '-_', '+/'), true);
        abort_unless(strlen($decode($data['keys']['p256dh']) ?: '') === 65 && strlen($decode($data['keys']['auth']) ?: '') === 16, 422, 'Invalid push encryption keys.');
        DB::transaction(function () use ($request, $data) {
            User::whereKey($request->user()->id)->lockForUpdate()->firstOrFail();
            $hash = hash('sha256', $data['endpoint']);
            $existing = PushSubscription::where('endpoint_hash', $hash)->first();
            abort_if($existing && $existing->user_id !== $request->user()->id, 409, 'Remove the previous browser subscription before switching accounts.');
            abort_if(! $existing && PushSubscription::where('user_id', $request->user()->id)->count() >= 10, 422, 'Remove an old device before adding another.');
            PushSubscription::updateOrCreate(['endpoint_hash' => $hash], ['user_id' => $request->user()->id, 'subscription' => $data]);
        });

        $request->session()->put('push_endpoint_hash', hash('sha256', $data['endpoint']));

        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        $data = $request->validate(['endpoint' => 'required|string|max:2000']);
        PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', hash('sha256', $data['endpoint']))->delete();
        if ($request->session()->get('push_endpoint_hash') === hash('sha256', $data['endpoint'])) {
            $request->session()->forget('push_endpoint_hash');
        }

        return response()->noContent();
    }
}
