<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $heading }}</title>
</head>
<body style="margin:0;background:#eef2ff;color:#0f172a;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
@php
    $brandName = $context['brand_name'] ?? config('app.name', 'Gym Atlas');
    $platformName = $context['platform_name'] ?? config('app.name', 'Gym Atlas');
    $categoryLabel = $context['category_label'] ?? str_replace('_', ' ', $context['category'] ?? 'Account update');
@endphp
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2ff;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#ffffff;border:1px solid #dbe3ff;border-radius:28px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.16);">
                <tr>
                    <td style="padding:34px;background:linear-gradient(135deg,#0b1020 0%,#172554 52%,#3641f5 100%);color:#ffffff;">
                        @if (!empty($context['gym_logo_url']))
                            <img src="{{ $context['gym_logo_url'] }}" alt="{{ $brandName }}" width="54" height="54" style="display:block;width:54px;height:54px;margin:0 0 16px;border-radius:16px;object-fit:cover;background:#ffffff;border:1px solid rgba(255,255,255,.35);">
                        @endif
                        <div style="display:inline-block;padding:7px 11px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.10);color:#c7d2fe;font-size:11px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;">{{ $categoryLabel }}</div>
                        <div style="margin-top:18px;font-size:30px;line-height:1.15;font-weight:800;letter-spacing:-.03em;">{{ $brandName }}</div>
                        @if (!empty($context['branch_name']))<div style="margin-top:8px;color:#dbeafe;font-size:15px;line-height:1.5;">{{ $context['branch_name'] }}</div>@endif
                        <div style="margin-top:22px;width:72px;height:4px;border-radius:999px;background:#d4af37;line-height:4px;font-size:4px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px;">
                        @if (!empty($context['recipient_name']))<p style="margin:0 0 12px;color:#334155;font-size:15px;line-height:1.6;">Hello {{ $context['recipient_name'] }},</p>@endif
                        <h1 style="margin:0 0 14px;color:#0f172a;font-size:28px;line-height:1.22;letter-spacing:-.03em;font-weight:800;">{{ $heading }}</h1>
                        <p style="margin:0;color:#475569;font-size:16px;line-height:1.75;">{{ $intro }}</p>
                        @if ($lines)
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:26px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
                                @foreach ($lines as $line)
                                    <tr><td style="padding:{{ $loop->first ? '18px 20px 10px' : ($loop->last ? '10px 20px 18px' : '10px 20px') }};color:#334155;font-size:14px;line-height:1.65;font-weight:600;">{{ $line }}</td></tr>
                                @endforeach
                            </table>
                        @endif
                        @if (!empty($context['action_url']))
                            <p style="margin:30px 0 0;"><a href="{{ $context['action_url'] }}" style="display:inline-block;padding:15px 24px;border-radius:14px;background:#3641f5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;box-shadow:0 12px 26px rgba(54,65,245,.28);">{{ $context['action_label'] ?? 'View details' }}</a></p>
                            <p style="margin:18px 0 0;color:#64748b;font-size:13px;line-height:1.65;">If the button does not open, copy this secure link into your browser:<br><span style="color:#334155;word-break:break-all;">{{ $context['action_url'] }}</span></p>
                        @endif
                        @if (!empty($context['support_note']))<div style="margin-top:24px;padding:16px 18px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:13px;line-height:1.65;">{{ $context['support_note'] }}</div>@endif
                    </td>
                </tr>
                <tr>
                    <td style="padding:21px 34px;border-top:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.65;">Sent by {{ $brandName }}@if ($brandName !== $platformName) using {{ $platformName }}@endif. This is an automated account message.</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
