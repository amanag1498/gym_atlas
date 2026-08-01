<!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Independent coaching invitation</title></head>
<body style="margin:0;background:#f5f3ff;color:#202034;font-family:Arial,Helvetica,sans-serif;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="padding:32px 12px;background:#f5f3ff;"><tr><td align="center">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:640px;background:#fff;border:1px solid #e7e2f4;border-radius:20px;overflow:hidden;box-shadow:0 14px 40px rgba(57,35,98,.1);">
        <tr><td style="padding:30px 34px;background:#2d1f4f;color:#fff;"><div style="font-size:12px;letter-spacing:1.7px;text-transform:uppercase;color:#c9b7ef;">Independent coaching</div><div style="margin-top:9px;font-size:27px;font-weight:700;">{{ $invitation->trainer->name }}</div></td></tr>
        <tr><td style="padding:34px;">
            <p style="margin:0 0 12px;font-size:15px;">Hello {{ $invitation->invited_name }},</p>
            <h1 style="margin:0 0 14px;font-size:25px;line-height:1.3;color:#2d1f4f;">A verified trainer wants to coach you</h1>
            <p style="margin:0;color:#5f5a70;font-size:16px;line-height:1.7;">{{ $invitation->trainer->name }} invited you to connect directly for independent coaching on {{ config('app.name', 'Atlas') }}.</p>
            @if(data_get($invitation->payload, 'message'))<div style="margin-top:22px;padding:16px 18px;border-left:4px solid #7c5ac7;background:#faf8ff;color:#4f4760;line-height:1.6;">{{ data_get($invitation->payload, 'message') }}</div>@endif
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:24px;background:#faf8ff;border:1px solid #ebe5f7;border-radius:13px;">
                <tr><td style="padding:16px 18px 8px;color:#686176;font-size:14px;">Trainer</td><td align="right" style="padding:16px 18px 8px;font-weight:700;">{{ $invitation->trainer->name }}</td></tr>
                <tr><td style="padding:8px 18px;color:#686176;font-size:14px;">Connection</td><td align="right" style="padding:8px 18px;font-weight:700;">Independent</td></tr>
                <tr><td style="padding:8px 18px 16px;color:#686176;font-size:14px;">Review before</td><td align="right" style="padding:8px 18px 16px;font-weight:700;">{{ $invitation->expires_at->format('d M Y') }}</td></tr>
            </table>
            <p style="margin:28px 0 0;"><a href="{{ $reviewUrl }}" style="display:inline-block;padding:14px 22px;border-radius:10px;background:#7152b7;color:#fff;text-decoration:none;font-weight:700;">Review coaching invitation</a></p>
            <p style="margin:24px 0 0;color:#667085;font-size:13px;line-height:1.6;">This connection is separate from every gym membership and gym-assigned trainer. Accepting or rejecting it will not change either.</p>
        </td></tr>
        <tr><td style="padding:20px 34px;border-top:1px solid #f0ecf7;color:#726b80;font-size:12px;">Sent securely by {{ config('app.name', 'Atlas') }}. Ignore this message if you did not expect it.</td></tr>
    </table>
</td></tr></table>
</body></html>
