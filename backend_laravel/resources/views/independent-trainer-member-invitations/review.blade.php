<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Independent coaching invitation</title></head>
<body style="font-family:Arial,sans-serif;max-width:620px;margin:48px auto;padding:0 20px;color:#172033">
<h1>Independent coaching invitation</h1>
@if (session('status'))
<p>{{ session('status') }}</p>
@elseif ($invitation->status !== 'pending' || $invitation->expires_at->isPast())
<p>This invitation is no longer active.</p>
@else
<p><strong>{{ $invitation->trainer->name }}</strong>, a verified independent trainer, wants to add you as a coaching member.</p>
<p>This connection is separate from all gym memberships and gym-assigned trainers. Accepting it will not change either.</p>
@if (data_get($invitation->payload, 'message'))<p>{{ data_get($invitation->payload, 'message') }}</p>@endif
<p>Shared coaching areas: {{ implode(', ', data_get($invitation->payload, 'sharing_permissions', [])) }}.</p>
<form method="POST" action="{{ $actionUrl }}">@csrf
<button name="decision" value="accept" type="submit">Approve coaching connection</button>
<button name="decision" value="reject" type="submit" style="margin-left:12px">Decline</button>
</form>
@endif
</body></html>
