@php
    $brand = $brand ?? app(\App\Services\EmailBranding::class)->get();
    $accent = $brand['accent_color'];
@endphp
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>{{ $brand['brand_name'] }}</title>
<style>
    .content p { margin: 0 0 16px; }
    .content h2 { margin: 28px 0 12px; font-size: 22px; line-height: 1.3; color: #0f172a; }
    .content h3 { margin: 24px 0 10px; font-size: 18px; line-height: 1.35; color: #0f172a; }
    .content h4 { margin: 20px 0 8px; font-size: 16px; color: #0f172a; }
    .content a { color: {{ $accent }}; font-weight: bold; }
    .content ul, .content ol { margin: 0 0 16px; padding-left: 22px; }
    .content li { margin: 0 0 6px; }
    .content blockquote { margin: 0 0 16px; padding: 10px 16px; border-left: 4px solid {{ $accent }}; background: #f8fafc; color: #334155; }
    .content table { width: 100%; border-collapse: collapse; margin: 0 0 16px; }
    .content td, .content th { padding: 8px 10px; border: 1px solid #e2e8f0; text-align: left; vertical-align: top; }
    .content hr { border: 0; border-top: 1px solid #e2e8f0; margin: 24px 0; }
    .content mark { background: #fef3c7; }
    @media (max-width: 620px) { .card { padding: 24px 20px !important; } }
</style>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;color:#334155;font-family:Arial,Helvetica,sans-serif;font-size:16px;line-height:1.65">
@if (! empty($preheader))
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{{ $preheader }}</div>
@endif
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9">
<tr><td align="center" style="padding:24px 12px">
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:640px">
        <tr><td style="background:{{ $brand['header_background'] }};border-top:5px solid {{ $accent }};border-radius:12px 12px 0 0;padding:22px 32px;border-left:1px solid #e2e8f0;border-right:1px solid #e2e8f0">
            @if ($brand['logo_url'])
                <img src="{{ $brand['logo_url'] }}" alt="{{ $brand['brand_name'] }}" height="48" style="display:block;height:48px;width:auto;max-width:240px;border:0">
            @else
                <span style="font-size:20px;font-weight:bold;letter-spacing:0.5px;color:{{ $accent }}">{{ $brand['brand_name'] }}</span>
            @endif
        </td></tr>
        <tr><td class="card content" style="background:#ffffff;padding:32px;border:1px solid #e2e8f0;border-top:0;border-radius:0 0 12px 12px">
            {{-- Content is sanitized by EmailContent before rendering. --}}
            {!! $content !!}
        </td></tr>
        <tr><td style="padding:20px 32px;text-align:center;font-size:12px;line-height:1.6;color:#64748b">
            <p style="margin:0 0 6px;font-weight:bold;color:#475569">{{ $brand['brand_name'] }}</p>
            @if (trim($brand['footer_text']) !== '')
                <p style="margin:0 0 6px">{!! nl2br(e($brand['footer_text'])) !!}</p>
            @endif
            @if (! empty($preferencesUrl))
                <p style="margin:0"><a href="{{ $preferencesUrl }}" style="color:#64748b">Manage your message preferences</a></p>
            @endif
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
