<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Trainer invitation from {{ $invitation->gym->name }}</title></head>
<body style="margin:0;background:#f3f6f8;color:#17212b;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px;background:#f3f6f8;"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border:1px solid #e4e9ee;border-radius:20px;overflow:hidden;box-shadow:0 14px 40px rgba(15,23,42,.09);">
        <tr><td style="padding:30px 34px;background:#102a24;color:#fff;"><div style="font-size:12px;letter-spacing:1.8px;text-transform:uppercase;color:#9dd8c3;">Trainer team invitation</div><div style="margin-top:9px;font-size:27px;font-weight:700;">{{ $invitation->gym->name }}</div>@if($invitation->branch)<div style="margin-top:6px;color:#d1e8df;font-size:14px;">{{ $invitation->branch->name }}</div>@endif</td></tr>
        <tr><td style="padding:34px;">
            <p style="margin:0 0 12px;font-size:15px;">Hello {{ $invitation->invited_name }},</p>
            <h1 style="margin:0 0 14px;font-size:25px;line-height:1.3;color:#102a24;">Join {{ $invitation->gym->name }} as a trainer</h1>
            <p style="margin:0;color:#52606d;font-size:16px;line-height:1.7;">Review the workspace assignment before approving. Your trainer profile will not be attached to this gym until you accept.</p>
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;background:#f7faf9;border:1px solid #ddebe5;border-radius:13px;">
                <tr><td style="padding:16px 18px 8px;color:#52606d;font-size:14px;">Gym</td><td align="right" style="padding:16px 18px 8px;font-weight:700;">{{ $invitation->gym->name }}</td></tr>
                @if($invitation->branch)<tr><td style="padding:8px 18px;color:#52606d;font-size:14px;">Branch</td><td align="right" style="padding:8px 18px;font-weight:700;">{{ $invitation->branch->name }}</td></tr>@endif
                @if(data_get($invitation->payload, 'specialization'))<tr><td style="padding:8px 18px;color:#52606d;font-size:14px;">Specialization</td><td align="right" style="padding:8px 18px;font-weight:700;">{{ data_get($invitation->payload, 'specialization') }}</td></tr>@endif
                <tr><td style="padding:8px 18px 16px;color:#52606d;font-size:14px;">Approval expires</td><td align="right" style="padding:8px 18px 16px;font-weight:700;">{{ $invitation->expires_at->format('d M Y') }}</td></tr>
            </table>
            <p style="margin:28px 0 0;"><a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 22px;border-radius:10px;background:#16a36a;color:#fff;text-decoration:none;font-weight:700;">Review trainer invitation</a></p>
            <p style="margin:24px 0 0;color:#667085;font-size:13px;line-height:1.6;">Accepting makes this a gym-managed trainer profile. If you currently coach members independently, active independent relationships must be resolved before gym assignment.</p>
        </td></tr>
        <tr><td style="padding:20px 34px;border-top:1px solid #edf1f4;color:#667085;font-size:12px;">Sent by {{ $invitation->gym->name }} using {{ config('app.name', 'Atlas') }}.</td></tr>
    </table>
</td></tr></table>
</body></html>
