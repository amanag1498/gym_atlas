<p>Hello {{ $invitation->invited_name }},</p>
<p>{{ $invitation->gym->name }} has invited you to join as a member. Review and approve your enrollment before {{ $invitation->expires_at->format('d M Y') }}.</p>
<p><a href="{{ $reviewUrl }}">Review enrollment</a></p>
<p>If you were not expecting this invitation, you can safely ignore this email.</p>
