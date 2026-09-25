<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Independent coaching invitation</title>
</head>
<body style="margin:0;background:#f1f5f9;color:#0f172a;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f1f5f9;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#ffffff;border:1px solid #e2e8f0;border-radius:28px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.16);">
                <tr>
                    <td style="padding:34px;background:linear-gradient(135deg,#111827 0%,#2d1f4f 48%,#6d5dfc 100%);color:#ffffff;">
                        <div style="display:inline-block;padding:7px 11px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.10);color:#ddd6fe;font-size:11px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;">Independent coaching</div>
                        <div style="margin-top:18px;font-size:30px;line-height:1.15;font-weight:800;letter-spacing:-.03em;">{{ $invitation->trainer->name }}</div>
                        <div style="margin-top:8px;color:#ede9fe;font-size:15px;line-height:1.5;">Private coaching request on Gym Atlas</div>
                        <div style="margin-top:22px;width:72px;height:4px;border-radius:999px;background:#d4af37;line-height:4px;font-size:4px;">&nbsp;</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px;">
                        <p style="margin:0 0 12px;color:#334155;font-size:15px;line-height:1.6;">Hello {{ $invitation->invited_name }},</p>
                        <h1 style="margin:0 0 14px;color:#0f172a;font-size:28px;line-height:1.22;letter-spacing:-.03em;font-weight:800;">A trainer wants to coach you directly</h1>
                        <p style="margin:0;color:#475569;font-size:16px;line-height:1.75;">{{ $invitation->trainer->name }} invited you to connect for independent coaching on Gym Atlas. Review the invitation before the connection is created.</p>
                        @if(data_get($invitation->payload, 'message'))
                            <div style="margin-top:24px;padding:18px 20px;border-radius:18px;background:#faf5ff;border:1px solid #e9d5ff;color:#4c1d95;font-size:14px;line-height:1.7;">{{ data_get($invitation->payload, 'message') }}</div>
                        @endif
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:26px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
                            @include('mail._detail-row', ['label' => 'Trainer', 'value' => $invitation->trainer->name, 'padding' => '18px 20px 10px'])
                            @include('mail._detail-row', ['label' => 'Connection', 'value' => 'Independent'])
                            @include('mail._detail-row', ['label' => 'Review before', 'value' => $invitation->expires_at->format('d M Y'), 'padding' => '10px 20px 18px'])
                        </table>
                        <p style="margin:30px 0 0;"><a href="{{ $reviewUrl }}" style="display:inline-block;padding:15px 24px;border-radius:14px;background:#6d5dfc;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;box-shadow:0 12px 26px rgba(109,93,252,.28);">Review coaching invitation</a></p>
                        <p style="margin:18px 0 0;color:#64748b;font-size:13px;line-height:1.65;">If the button does not open, copy this secure link into your browser:<br><span style="color:#334155;word-break:break-all;">{{ $reviewUrl }}</span></p>
                        <div style="margin-top:24px;padding:16px 18px;border-radius:16px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:13px;line-height:1.65;">This connection is separate from gym memberships and gym-assigned trainers. Accepting or rejecting it will not change either.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:21px 34px;border-top:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.65;">Sent securely by Gym Atlas. Ignore this message if you did not expect it.</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
