<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Join {{ $invitation->gym->name }}</title>
</head>
<body style="margin:0;background:#eef2ff;color:#0f172a;font-family:Arial,Helvetica,sans-serif;-webkit-font-smoothing:antialiased;">
<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#eef2ff;padding:28px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:660px;background:#ffffff;border:1px solid #dbe3ff;border-radius:28px;overflow:hidden;box-shadow:0 24px 70px rgba(15,23,42,.16);">
                <tr>
                    <td style="padding:0;background:#0b1020;">
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#0b1020;">
                            <tr>
                                <td style="padding:34px 34px 28px;background:linear-gradient(135deg,#0b1020 0%,#172554 52%,#3641f5 100%);color:#ffffff;">
                                    <div style="display:inline-block;padding:7px 11px;border:1px solid rgba(255,255,255,.24);border-radius:999px;background:rgba(255,255,255,.10);color:#c7d2fe;font-size:11px;letter-spacing:.16em;text-transform:uppercase;font-weight:800;">Membership invitation</div>
                                    <div style="margin-top:18px;font-size:30px;line-height:1.15;font-weight:800;letter-spacing:-.03em;">{{ $invitation->gym->name }}</div>
                                    @if($invitation->branch)<div style="margin-top:8px;color:#dbeafe;font-size:15px;line-height:1.5;">{{ $invitation->branch->name }}</div>@endif
                                    <div style="margin-top:22px;width:72px;height:4px;border-radius:999px;background:#d4af37;line-height:4px;font-size:4px;">&nbsp;</div>
                                </td>
                            </tr>
                        </table>
                    </td>
                </tr>
                <tr>
                    <td style="padding:34px;">
                        <p style="margin:0 0 12px;color:#334155;font-size:15px;line-height:1.6;">Hello {{ $invitation->invited_name }},</p>
                        <h1 style="margin:0 0 14px;color:#0f172a;font-size:28px;line-height:1.22;letter-spacing:-.03em;font-weight:800;">Approve your Gym Atlas membership</h1>
                        <p style="margin:0;color:#475569;font-size:16px;line-height:1.75;">{{ $invitation->gym->name }} has prepared a membership invitation for you. Review the details first; nothing is created until you approve.</p>
                        <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="margin-top:26px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:18px;overflow:hidden;">
                            @include('mail._detail-row', ['label' => 'Gym', 'value' => $invitation->gym->name, 'padding' => '18px 20px 10px'])
                            @if($invitation->branch) @include('mail._detail-row', ['label' => 'Branch', 'value' => $invitation->branch->name]) @endif
                            @if($membershipPlan) @include('mail._detail-row', ['label' => 'Plan', 'value' => $membershipPlan->name]) @endif
                            @if($invitation->assignedTrainer) @include('mail._detail-row', ['label' => 'Trainer', 'value' => $invitation->assignedTrainer->name]) @endif
                            @if(data_get($invitation->payload, 'start_date')) @include('mail._detail-row', ['label' => 'Start date', 'value' => \Carbon\Carbon::parse(data_get($invitation->payload, 'start_date'))->format('d M Y')]) @endif
                            @include('mail._detail-row', ['label' => 'Expires', 'value' => $invitation->expires_at->format('d M Y'), 'padding' => '10px 20px 18px'])
                        </table>
                        <p style="margin:30px 0 0;"><a href="{{ $reviewUrl }}" style="display:inline-block;padding:15px 24px;border-radius:14px;background:#3641f5;color:#ffffff;text-decoration:none;font-size:15px;font-weight:800;box-shadow:0 12px 26px rgba(54,65,245,.28);">Review membership invitation</a></p>
                        <p style="margin:18px 0 0;color:#64748b;font-size:13px;line-height:1.65;">If the button does not open, copy this secure link into your browser:<br><span style="color:#334155;word-break:break-all;">{{ $reviewUrl }}</span></p>
                        <div style="margin-top:24px;padding:16px 18px;border-radius:16px;background:#fff7ed;border:1px solid #fed7aa;color:#7c2d12;font-size:13px;line-height:1.65;">If you were not expecting this invitation, ignore this email. Your current memberships and coaching connections are not changed.</div>
                    </td>
                </tr>
                <tr>
                    <td style="padding:21px 34px;border-top:1px solid #e2e8f0;background:#f8fafc;color:#64748b;font-size:12px;line-height:1.65;">Sent by {{ $invitation->gym->name }} using Gym Atlas.</td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
