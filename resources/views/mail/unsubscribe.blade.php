<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Message preferences · JK Michaels</title></head>
<body style="font:16px/1.6 Arial,sans-serif;background:#f8fafc;color:#0f172a;padding:24px">
<main style="max-width:520px;margin:60px auto;background:white;padding:32px;border:1px solid #e2e8f0;border-radius:16px">
<p style="color:#c2410c;font-weight:bold">JK MICHAELS</p><h1>Message preferences</h1>
@if ($done)<p>Your {{ $channel === 'mail' ? 'email' : 'WhatsApp' }} campaign subscription has been turned off.</p>
@else<p>Unsubscribe from {{ $channel === 'mail' ? 'email' : 'WhatsApp' }} campaigns?</p><form method="post">@csrf<button style="background:#c2410c;color:white;border:0;border-radius:8px;padding:12px 20px;font-size:16px">Unsubscribe</button></form>@endif
</main></body></html>
