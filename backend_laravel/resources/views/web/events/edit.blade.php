@extends('layouts.panel')
@section('content')
<x-premium-card class="p-6">
    <h2 class="text-2xl font-semibold text-slate-950 dark:text-white">Edit event</h2>
    <p class="mt-2 text-sm text-slate-500">Schedule or location changes notify confirmed and waitlisted members and rebuild pending reminders.</p>
    @if($errors->any())<div class="mt-4 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ $errors->first() }}</div>@endif
    <form method="POST" action="{{ $panel==='admin' ? route('web.admin.events.update',$event) : route('web.gym.events.update',array_merge(request()->only(['gym','branch']),['event'=>$event])) }}" class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">@csrf @method('PUT')
        @if($panel==='gym')<input type="hidden" name="gym_id" value="{{ $gym->id }}">@endif
        <label class="text-sm font-medium">Title<input required name="title" value="{{ old('title',$event->title) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Category<input name="category" value="{{ old('category',$event->category) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Host<select name="host_user_id" class="form-control mt-1 w-full"><option value="">No named host</option>@foreach($hosts as $host)<option value="{{ $host->id }}" @selected((int)old('host_user_id',$event->host_user_id)===$host->id)>{{ $host->name }}</option>@endforeach</select></label>
        <label class="text-sm font-medium">Starts<input required type="datetime-local" name="starts_at" value="{{ old('starts_at',$event->starts_at->timezone($event->timezone)->format('Y-m-d\\TH:i')) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Ends<input required type="datetime-local" name="ends_at" value="{{ old('ends_at',$event->ends_at->timezone($event->timezone)->format('Y-m-d\\TH:i')) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Timezone<input required name="timezone" value="{{ old('timezone',$event->timezone) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Capacity<input type="number" min="1" name="capacity" value="{{ old('capacity',$event->capacity) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Booking opens<input type="datetime-local" name="booking_opens_at" value="{{ old('booking_opens_at',$event->booking_opens_at?->timezone($event->timezone)->format('Y-m-d\\TH:i')) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Booking closes<input type="datetime-local" name="booking_closes_at" value="{{ old('booking_closes_at',$event->booking_closes_at?->timezone($event->timezone)->format('Y-m-d\\TH:i')) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Cancel until<input type="datetime-local" name="cancellation_closes_at" value="{{ old('cancellation_closes_at',$event->cancellation_closes_at?->timezone($event->timezone)->format('Y-m-d\\TH:i')) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Booking type<select name="pricing_type" class="form-control mt-1 w-full"><option value="free" @selected($event->pricing_type==='free')>Free</option><option value="pay_at_venue" @selected($event->pricing_type==='pay_at_venue')>Pay at venue</option></select></label>
        <label class="text-sm font-medium">Venue price<input type="number" step="0.01" name="price_amount" value="{{ old('price_amount',$event->price_amount) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Location<input name="location_name" value="{{ old('location_name',$event->location_name) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium md:col-span-2">Cover image URL<input type="url" name="cover_image_url" value="{{ old('cover_image_url',$event->cover_image_url) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium md:col-span-2">Address<input name="address" value="{{ old('address',$event->address) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Latitude<input type="number" step="0.0000001" name="latitude" value="{{ old('latitude',$event->latitude) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Longitude<input type="number" step="0.0000001" name="longitude" value="{{ old('longitude',$event->longitude) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium">Payment note<input name="payment_note" value="{{ old('payment_note',$event->payment_note) }}" class="form-control mt-1 w-full"></label>
        <label class="text-sm font-medium md:col-span-2 xl:col-span-3">Description<textarea name="description" rows="4" class="form-control mt-1 w-full">{{ old('description',$event->description) }}</textarea></label>
        <input type="hidden" name="waitlist_enabled" value="0"><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="waitlist_enabled" value="1" @checked(old('waitlist_enabled',$event->waitlist_enabled))> Enable waitlist</label>
        <select name="status" class="form-control"><option value="published" @selected($event->status==='published')>Published</option><option value="draft" @selected($event->status==='draft')>Draft</option></select>
        <button class="rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-semibold text-white">Save changes</button>
    </form>
</x-premium-card>
@endsection
