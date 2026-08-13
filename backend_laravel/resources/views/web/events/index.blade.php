@extends('layouts.panel')

@section('content')
<div class="space-y-6">
    <section class="panel-hero">
        <div class="panel-toolbar-chip">Events & classes</div>
        <h2 class="mt-4 text-3xl font-semibold text-slate-950 dark:text-white">{{ $panel === 'admin' ? 'Global event calendar' : 'Gym event calendar' }}</h2>
        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">Publish free reservations or pay-at-venue sessions. Online payment is intentionally not collected.</p>
    </section>

    @if ($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"><ul class="list-disc pl-5">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <x-premium-card class="p-6">
        <h3 class="panel-section-title">Create event</h3>
        <form method="POST" action="{{ $panel === 'admin' ? route('web.admin.events.store') : route('web.gym.events.store', request()->only(['gym','branch'])) }}" class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            @csrf
            @if ($panel === 'gym')<input type="hidden" name="gym_id" value="{{ $gym->id }}">@if($branch)<input type="hidden" name="branch_id" value="{{ $branch->id }}">@endif @endif
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Title<input required name="title" value="{{ old('title') }}" class="form-control mt-1 w-full" placeholder="Morning Zumba"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Category<input name="category" value="{{ old('category') }}" class="form-control mt-1 w-full" placeholder="Zumba, workshop, community"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Host<select name="host_user_id" class="form-control mt-1 w-full"><option value="">No named host</option>@foreach($hosts as $host)<option value="{{ $host->id }}">{{ $host->name }}</option>@endforeach</select></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Starts<input required type="datetime-local" name="starts_at" value="{{ old('starts_at') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Ends<input required type="datetime-local" name="ends_at" value="{{ old('ends_at') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Timezone<input required name="timezone" value="{{ old('timezone', $gym->timezone ?? config('app.timezone')) }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Capacity<input type="number" min="1" name="capacity" value="{{ old('capacity') }}" class="form-control mt-1 w-full" placeholder="Unlimited"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Booking opens<input type="datetime-local" name="booking_opens_at" value="{{ old('booking_opens_at') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Booking closes<input type="datetime-local" name="booking_closes_at" value="{{ old('booking_closes_at') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Cancel until<input type="datetime-local" name="cancellation_closes_at" value="{{ old('cancellation_closes_at') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Booking type<select name="pricing_type" class="form-control mt-1 w-full"><option value="free">Free</option><option value="pay_at_venue">Pay at venue</option></select></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Venue price (INR)<input type="number" step="0.01" min="0" name="price_amount" value="{{ old('price_amount') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Location name<input name="location_name" value="{{ old('location_name') }}" class="form-control mt-1 w-full" placeholder="Studio A"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">Cover image URL<input type="url" name="cover_image_url" value="{{ old('cover_image_url') }}" class="form-control mt-1 w-full" placeholder="https://..."></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2">Address<input name="address" value="{{ old('address') }}" class="form-control mt-1 w-full"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Latitude<input type="number" step="0.0000001" name="latitude" value="{{ old('latitude') }}" class="form-control mt-1 w-full" placeholder="28.6139000"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Longitude<input type="number" step="0.0000001" name="longitude" value="{{ old('longitude') }}" class="form-control mt-1 w-full" placeholder="77.2090000"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200">Payment note<input name="payment_note" value="{{ old('payment_note') }}" class="form-control mt-1 w-full" placeholder="Pay at reception before class"></label>
            <label class="text-sm font-medium text-slate-700 dark:text-slate-200 md:col-span-2 xl:col-span-3">Description<textarea name="description" rows="3" class="form-control mt-1 w-full">{{ old('description') }}</textarea></label>
            <div class="flex items-center gap-4 md:col-span-2 xl:col-span-3">
                <input type="hidden" name="waitlist_enabled" value="0">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="waitlist_enabled" value="1" @checked(old('waitlist_enabled', true))> Enable waitlist</label>
                <select name="status" class="form-control"><option value="published">Publish now</option><option value="draft">Save draft</option></select>
                <button class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white">Create event</button>
            </div>
        </form>
    </x-premium-card>

    <x-table-wrapper class="overflow-hidden p-0">
        <div class="border-b border-slate-200 px-5 py-4 dark:border-slate-800"><h3 class="panel-section-title">Complete event roster</h3><p class="panel-section-copy">All scheduled events, including drafts, completed and cancelled records.</p></div>
        <div class="overflow-x-auto"><table class="panel-table min-w-[900px]"><thead><tr><th>Event</th><th>Schedule</th><th>Host</th><th>Bookings</th><th>Type</th><th>Status</th><th></th></tr></thead><tbody>
        @forelse($events as $event)<tr>
            <td><div class="font-semibold text-slate-950 dark:text-white">{{ $event->title }}</div><div class="text-xs text-slate-500">{{ $event->location_name ?: 'Location not set' }}</div></td>
            <td>{{ $event->starts_at->timezone($event->timezone)->format('d M Y, g:i A') }}<div class="text-xs text-slate-500">{{ $event->timezone }}</div></td>
            <td>{{ $event->host?->name ?: 'Unassigned' }}</td><td>{{ $event->reserved_count }} / {{ $event->capacity ?: 'Unlimited' }}</td>
            <td>{{ $event->pricing_type === 'pay_at_venue' ? '₹'.number_format((float)$event->price_amount,2).' at venue' : 'Free' }}</td><td><x-status-badge :label="$event->status" /></td>
            <td><a class="font-semibold text-brand-600" href="{{ $panel === 'admin' ? route('web.admin.events.show',$event) : route('web.gym.events.show', array_merge(request()->only(['gym','branch']),['event'=>$event])) }}">Roster</a></td>
        </tr>@empty<tr><td colspan="7" class="py-10 text-center text-slate-500">No events created yet.</td></tr>@endforelse
        </tbody></table></div>
        <div class="p-4">{{ $events->withQueryString()->links() }}</div>
    </x-table-wrapper>
</div>
@endsection
