<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Http\Request;

class DeviceSessionService
{
    public function disconnect(Request $request): void
    {
        if ($request->user() && $hash = $request->session()->get('push_endpoint_hash')) {
            PushSubscription::where('user_id', $request->user()->id)->where('endpoint_hash', $hash)->delete();
        }
        $request->session()->forget('push_endpoint_hash');
    }
}
