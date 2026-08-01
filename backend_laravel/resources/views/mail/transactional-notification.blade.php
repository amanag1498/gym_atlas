<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;background:#f4f7fb;color:#182230;font-family:Arial,Helvetica,sans-serif;">
@php
    $brandName = $context['brand_name'] ?? config('app.name', 'Atlas');
    $platformName = $context['platform_name'] ?? config('app.name', 'Atlas');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f7fb;padding:32px 12px;">
    <tr><td align="center">
        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:620px;background:#ffffff;border:1px solid #e5eaf0;border-radius:18px;overflow:hidden;box-shadow:0 12px 36px rgba(15,23,42,.08);">
            <tr><td style="padding:28px 32px;background:#101828;color:#ffffff;">
                @if (!empty($context['gym_logo_url']))<img src="{{ $context['gym_logo_url'] }}" alt="{{ $brandName }}" width="48" height="48" style="display:block;width:48px;height:48px;margin:0 0 14px;border-radius:12px;object-fit:cover;background:#ffffff;">@endif
                <div style="font-size:12px;letter-spacing:1.6px;text-transform:uppercase;color:#9fb3c8;">{{ $context['category_label'] ?? str_replace('_', ' ', $context['category'] ?? 'Account update') }}</div>
                <div style="margin-top:8px;font-size:24px;font-weight:700;line-height:1.25;">{{ $brandName }}</div>
                @if (!empty($context['branch_name']))<div style="margin-top:5px;font-size:14px;color:#d0d8e4;">{{ $context['branch_name'] }}</div>@endif
            </td></tr>
            <tr><td style="padding:32px;">
                @if (!empty($context['recipient_name']))<p style="margin:0 0 12px;font-size:15px;">Hello {{ $context['recipient_name'] }},</p>@endif
                <h1 style="margin:0 0 14px;font-size:24px;line-height:1.3;color:#101828;">{{ $heading }}</h1>
                <p style="margin:0;color:#475467;font-size:16px;line-height:1.7;">{{ $intro }}</p>
                @if ($lines)
                    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;background:#f8fafc;border:1px solid #e8edf3;border-radius:12px;">
                        @foreach ($lines as $line)
                            <tr><td style="padding:{{ $loop->first ? '16px 18px 9px' : ($loop->last ? '9px 18px 16px' : '9px 18px') }};font-size:14px;line-height:1.5;color:#344054;">{{ $line }}</td></tr>
                        @endforeach
                    </table>
                @endif
                @if (!empty($context['action_url']))
                    <p style="margin:28px 0 0;"><a href="{{ $context['action_url'] }}" style="display:inline-block;padding:13px 20px;border-radius:10px;background:#16a34a;color:#ffffff;text-decoration:none;font-weight:700;">{{ $context['action_label'] ?? 'View details' }}</a></p>
                @endif
                @if (!empty($context['support_note']))<p style="margin:24px 0 0;color:#667085;font-size:13px;line-height:1.6;">{{ $context['support_note'] }}</p>@endif
            </td></tr>
            <tr><td style="padding:20px 32px;border-top:1px solid #eef2f6;color:#667085;font-size:12px;line-height:1.6;">
                Sent by {{ $brandName }}@if ($brandName !== $platformName) using {{ $platformName }}@endif. This is an automated account message.
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
