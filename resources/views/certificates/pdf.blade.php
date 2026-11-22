<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8"><style>
@page { margin: 28px; } body { font-family: DejaVu Sans, sans-serif; color: #17345b; }
.frame { border: 8px solid #17345b; padding: 12px; } .inner { border: 1px solid #bd9242; padding: 32px; text-align: center; }
.brand { font-size: 13px; letter-spacing: 3px; } h1 { font-size: 34px; font-weight: normal; margin: 22px 0; }
.name { font-size: 28px; color: #0f766e; margin: 18px 0; } .program { font-size: 20px; margin: 16px 0 28px; }
p { font-size: 12px; } table { width: 100%; margin-top: 20px; border-top: 1px solid #ccd5de; padding-top: 18px; }
td { width: 33%; font-size: 11px; vertical-align: middle; } a { color: #0f766e; } img { width: 82px; height: 82px; }
</style></head><body><div class="frame"><div class="inner">
<div class="brand">JK MICHAELS INSTITUTE</div><h1>Certificate of Completion</h1>
<p>This certifies that</p><div class="name">{{ $certificate->user->first_name }} {{ $certificate->user->last_name }}</div>
<p>has successfully completed</p><div class="program">{{ $certificate->certifiable->title ?? $certificate->title }}</div>
<table><tr><td>Certificate number<br><strong>{{ $certificate->certificate_number }}</strong></td>
<td>Issued<br><strong>{{ $certificate->issued_at->format('F j, Y') }}</strong></td>
<td><img src="{{ $qrData }}" alt="Verification QR code"><br>Scan to verify</td></tr></table>
<p><a href="{{ $verifyUrl }}">Verify this credential online</a></p>
</div></div></body></html>
