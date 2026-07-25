<p>{{ $intro }}</p>
@if ($lines)
<ul>
@foreach ($lines as $line)<li>{{ $line }}</li>@endforeach
</ul>
@endif
<p>— {{ config('app.name') }}</p>
