<?php

return [
    'vapid' => [
        'subject' => env('VAPID_SUBJECT', env('APP_URL')),
        'publicKey' => env('VAPID_PUBLIC_KEY'),
        'privateKey' => env('VAPID_PRIVATE_KEY'),
    ],
    'push_hosts' => ['fcm.googleapis.com', 'updates.push.services.mozilla.com', 'web.push.apple.com', '*.notify.windows.com'],
    'daily_message_limit' => 20,
];
