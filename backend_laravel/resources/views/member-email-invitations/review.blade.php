<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Gym enrollment</title></head>
<body style="font-family:Arial,sans-serif;max-width:620px;margin:48px auto;padding:0 20px;color:#172033">
<h1>Gym enrollment</h1>
@if (session('status'))<p>{{ session('status') }}</p>@elseif ($invitation->status !== 'pending' || $invitation->expires_at->isPast())<p>This invitation is no longer active.</p>@else
<p><strong>{{ $invitation->gym->name }}</strong> has invited you to join as a {{ !empty($trainerInvitation) ? 'trainer' : 'member' }}{{ $invitation->branch ? ' at '.$invitation->branch->name : '' }}.</p>
@if (empty($trainerInvitation) && $invitation->assignedTrainer)<p>Your assigned trainer will be {{ $invitation->assignedTrainer->name }}.</p>@endif
<p>Please confirm only if you want this gym to create your {{ !empty($trainerInvitation) ? 'trainer profile' : 'membership' }}.</p>
<form method="POST" action="{{ $actionUrl }}">@csrf<button name="decision" value="accept" type="submit">Approve enrollment</button><button name="decision" value="reject" type="submit" style="margin-left:12px">Decline</button></form>
@endif
</body></html>
