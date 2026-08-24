<?php

namespace App\Services\Events;

use App\Enums\NotificationType;
use App\Jobs\SendEventBookingAudienceNotification;
use App\Jobs\SendEventPublishedNotifications;
use App\Models\Branch;
use App\Models\Event;
use App\Models\EventBooking;
use App\Models\EventReminder;
use App\Models\MemberProfile;
use App\Models\TrainerProfile;
use App\Models\User;
use App\Services\Audit\AuditLogService;
use App\Services\Members\GymMemberAccessService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class EventService
{
    public function __construct(
        private readonly EventNotificationService $notifier,
        private readonly GymMemberAccessService $memberAccess,
        private readonly AuditLogService $audit,
    ) {}

    public function memberQuery(User $user): Builder
    {
        $profileQuery = MemberProfile::query()->where('user_id', $user->id);
        $profiles = $this->memberAccess->scopeAccessibleProfiles($profileQuery)->get(['gym_id', 'branch_id']);

        return $this->baseUpcomingQuery($user)->where(function (Builder $query) use ($profiles): void {
            $query->where(function (Builder $visible): void {
                $visible->where('scope', 'global')
                    ->where('app_visibility', 'all_atlas')
                    ->whereIn('booking_audience', ['atlas_members', 'anyone']);
            })->orWhere(function (Builder $legacy): void {
                $legacy->where('scope', 'global')->whereNull('app_visibility');
            });
            foreach ($profiles as $profile) {
                $query->orWhere(function (Builder $scope) use ($profile): void {
                    $scope->where('scope', 'gym')->where('gym_id', $profile->gym_id)
                        ->where('app_visibility', '!=', 'link_only')
                        ->where(fn (Builder $branch) => $branch->whereNull('branch_id')->when($profile->branch_id, fn (Builder $q) => $q->orWhere('branch_id', $profile->branch_id)));
                });
            }
        });
    }

    public function trainerQuery(User $user, bool $hostedOnly = false): Builder
    {
        $profiles = TrainerProfile::query()->where('user_id', $user->id)->where('is_active', true)
            ->where(fn (Builder $query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->where(fn (Builder $query) => $query->whereNull('gym_id')->orWhereHas('gym', fn (Builder $gym) => $gym
                ->where('is_active', true)->where('status', 'active')->where('operational_access_enabled', true)))
            ->get(['gym_id', 'branch_id']);
        $query = $this->baseUpcomingQuery($user);
        if ($hostedOnly) {
            return $query->where('host_user_id', $user->id);
        }

        return $query->where(function (Builder $query) use ($profiles, $user): void {
            $query->where('scope', 'global')->orWhere('host_user_id', $user->id);
            foreach ($profiles as $profile) {
                if (! $profile->gym_id) {
                    continue;
                }
                $query->orWhere(function (Builder $scope) use ($profile): void {
                    $scope->where('scope', 'gym')->where('gym_id', $profile->gym_id)
                        ->where(fn (Builder $branch) => $branch->whereNull('branch_id')->when($profile->branch_id, fn (Builder $q) => $q->orWhere('branch_id', $profile->branch_id)));
                });
            }
        });
    }

    public function book(User $user, Event $event, bool $publicLinkAccess = false, ?string $bookingPhone = null): EventBooking
    {
        return DB::transaction(function () use ($user, $event, $publicLinkAccess, $bookingPhone): EventBooking {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->ensureEventOperational($event);
            $available = $publicLinkAccess
                ? in_array($event->booking_audience, ['atlas_members', 'anyone'], true)
                : $this->memberQuery($user)->whereKey($event->id)->exists();
            if (! $available) {
                $this->invalid('event', 'This event is not available to your account.');
            }
            $this->ensureBookingOpen($event);
            $phone = preg_replace('/\D+/', '', (string) ($bookingPhone ?: $user->phone)) ?: null;
            if (! $phone || strlen($phone) < 7 || strlen($phone) > 15) {
                $this->invalid('phone', 'A valid mobile number is required for event booking.');
            }
            if (blank($user->phone) && filled($bookingPhone)) {
                $user->forceFill(['phone' => $phone])->save();
            }
            $existing = EventBooking::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['reserved', 'waitlisted', 'attended'], true)) {
                if ($existing->attendee_phone !== $phone) {
                    $existing->update(['attendee_phone' => $phone]);
                }

                return $existing->fresh(['event']);
            }

            $status = $this->nextBookingStatus($event);

            $booking = EventBooking::query()->updateOrCreate(['event_id' => $event->id, 'user_id' => $user->id], [
                'attendee_name' => $user->name, 'attendee_email' => $user->email, 'attendee_phone' => $phone,
                'booking_source' => 'member_app',
                'status' => $status, 'booked_at' => now(), 'cancelled_at' => null, 'cancellation_reason' => null,
                'price_amount_snapshot' => $event->price_amount, 'currency_snapshot' => $event->currency,
                'payment_note_snapshot' => $event->payment_note,
            ]);
            if ($status === 'reserved') {
                $this->scheduleReminders($booking, $event);
            }
            DB::afterCommit(fn () => $this->notifier->send($user, $event,
                $status === 'reserved' ? NotificationType::EventBookingConfirmed->value : NotificationType::EventWaitlisted->value,
                $status === 'reserved' ? 'Event booking confirmed' : 'You joined the waitlist',
                $status === 'reserved' ? "Your spot for {$event->title} is confirmed." : "You are on the waitlist for {$event->title}."));

            return $booking->fresh(['event']);
        });
    }

    public function publicEvent(string $publicToken): Event
    {
        $query = Event::query()
            ->with(['gym:id,name,logo,logo_url', 'branch:id,name', 'host:id,name,avatar'])
            ->withCount(['bookings as reserved_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended'])])
            ->where('public_token', $publicToken)
            ->where('public_booking_enabled', true)
            ->where('booking_audience', 'anyone')
            ->where('status', 'published')
            ->where('ends_at', '>=', now());
        $this->scopeOperationalEvents($query);

        return $query->firstOrFail();
    }

    public function shareableEvent(string $publicToken): Event
    {
        $query = Event::query()
            ->with(['gym:id,name,logo,logo_url', 'branch:id,name', 'host:id,name,avatar'])
            ->withCount(['bookings as reserved_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended'])])
            ->where('public_token', $publicToken)
            ->whereIn('booking_audience', ['atlas_members', 'anyone'])
            ->where('status', 'published')
            ->where('ends_at', '>=', now());
        $this->scopeOperationalEvents($query);

        return $query->firstOrFail();
    }

    public function memberLinkedEvent(string $publicToken): Event
    {
        return $this->shareableEvent($publicToken);
    }

    /** @return array{booking: EventBooking, manage_token: string} */
    public function bookGuest(Event $event, array $data): array
    {
        return DB::transaction(function () use ($event, $data): array {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $this->ensureEventOperational($event);
            if (! $event->public_booking_enabled || $event->booking_audience !== 'anyone') {
                $this->invalid('event', 'Public booking is not enabled for this event.');
            }
            $this->ensureBookingOpen($event);

            $email = Str::lower(trim((string) $data['email']));
            $phone = preg_replace('/\D+/', '', (string) $data['phone']) ?: null;
            if (! $phone || strlen($phone) < 7 || strlen($phone) > 15) {
                $this->invalid('phone', 'Enter a valid mobile number.');
            }
            $answers = (array) ($data['answers'] ?? []);
            $allowedAnswerKeys = [];
            foreach ((array) $event->registration_form_schema as $field) {
                $key = isset($field['key']) ? (string) $field['key'] : '';
                if ($key === '') {
                    continue;
                }
                $allowedAnswerKeys[] = $key;
                if (! empty($field['required']) && ! filled($answers[$key] ?? null)) {
                    $this->invalid('answers.'.$key, ($field['label'] ?? Str::headline($key)).' is required.');
                }
                if (($field['type'] ?? null) === 'select' && filled($answers[$key] ?? null)
                    && ! in_array($answers[$key], (array) ($field['options'] ?? []), true)) {
                    $this->invalid('answers.'.$key, 'Select a valid option.');
                }
            }
            $answers = array_intersect_key($answers, array_flip($allowedAnswerKeys));
            $attendeeKey = hash_hmac('sha256', $email, (string) config('app.key'));
            $existing = EventBooking::query()->where('event_id', $event->id)->where('attendee_key', $attendeeKey)->lockForUpdate()->first();
            if ($existing && in_array($existing->status, ['reserved', 'waitlisted', 'attended'], true)) {
                $this->invalid('email', 'A booking already exists for these contact details. Use its manage link instead.');
            }

            $status = $this->nextBookingStatus($event);
            $manageToken = Str::random(64);
            $attributes = [
                'user_id' => null,
                'attendee_name' => trim((string) $data['name']),
                'attendee_email' => $email,
                'attendee_phone' => $phone,
                'attendee_key' => $attendeeKey,
                'booking_source' => 'public_web',
                'manage_token_hash' => hash('sha256', $manageToken),
                'manage_token_ciphertext' => $manageToken,
                'registration_answers' => $answers ?: null,
                'status' => $status,
                'booked_at' => now(),
                'cancelled_at' => null,
                'cancellation_reason' => null,
                'price_amount_snapshot' => $event->price_amount,
                'currency_snapshot' => $event->currency,
                'payment_note_snapshot' => $event->payment_note,
            ];
            if ($existing) {
                $existing->update($attributes);
                $booking = $existing;
            } else {
                $booking = $event->bookings()->create($attributes);
            }
            if ($status === 'reserved') {
                $this->scheduleReminders($booking, $event);
            }
            DB::afterCommit(fn () => $this->notifier->sendBooking(
                $booking->fresh(['user']), $event,
                $status === 'reserved' ? NotificationType::EventBookingConfirmed->value : NotificationType::EventWaitlisted->value,
                $status === 'reserved' ? 'Event booking confirmed' : 'You joined the waitlist',
                $status === 'reserved' ? "Your spot for {$event->title} is confirmed." : "You are on the waitlist for {$event->title}.",
                $manageToken,
            ));

            return ['booking' => $booking->fresh(['event', 'user']), 'manage_token' => $manageToken];
        });
    }

    public function guestBooking(Event $event, EventBooking $booking, string $manageToken): EventBooking
    {
        if ($booking->event_id !== $event->id || ! $booking->manage_token_hash
            || ! hash_equals($booking->manage_token_hash, hash('sha256', $manageToken))) {
            abort(404);
        }

        return $booking->load(['event.gym', 'event.branch']);
    }

    public function eventByPublicTokenForManagement(string $publicToken): Event
    {
        return Event::query()->with(['gym', 'branch', 'host'])->where('public_token', $publicToken)->firstOrFail();
    }

    public function publicEventForClaim(string $publicToken, string $manageToken): Event
    {
        if (preg_match('/^[A-Za-z0-9]{64}$/', $manageToken) !== 1) {
            abort(404);
        }
        $event = $this->eventByPublicTokenForManagement($publicToken);
        $booking = EventBooking::query()
            ->where('event_id', $event->id)
            ->where('manage_token_hash', hash('sha256', $manageToken))
            ->firstOrFail();
        $this->guestBooking($event, $booking, $manageToken);

        return $event->load(['gym:id,name', 'branch:id,name', 'host:id,name,avatar'])
            ->loadCount(['bookings as reserved_count' => fn ($query) => $query->whereIn('status', ['reserved', 'attended'])]);
    }

    public function cancelGuest(Event $event, EventBooking $booking, string $manageToken): EventBooking
    {
        $this->guestBooking($event, $booking, $manageToken);

        return DB::transaction(function () use ($event, $booking, $manageToken): EventBooking {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $booking = EventBooking::query()->lockForUpdate()->findOrFail($booking->id);
            $this->guestBooking($event, $booking, $manageToken);
            $this->ensureCancellationOpen($event, $booking);
            $wasReserved = $booking->status === 'reserved';
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $booking->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
            if ($wasReserved) {
                $this->promoteWaitlist($event);
            }
            DB::afterCommit(fn () => $this->notifier->sendBooking(
                $booking->fresh(['user']), $event, NotificationType::EventBookingCancelled->value,
                'Event booking cancelled', "Your booking for {$event->title} was cancelled.",
            ));

            return $booking->fresh();
        });
    }

    public function claimGuestBooking(User $user, Event $event, string $manageToken): EventBooking
    {
        return DB::transaction(function () use ($user, $event, $manageToken): EventBooking {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $guest = EventBooking::query()->where('event_id', $event->id)
                ->where('manage_token_hash', hash('sha256', $manageToken))->lockForUpdate()->first();
            if (! $guest || $guest->user_id || ! in_array($guest->status, ['reserved', 'waitlisted'], true)) {
                $this->invalid('manage_token', 'This guest booking cannot be claimed.');
            }

            $existing = EventBooking::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->first();
            if (! $existing) {
                $guest->update([
                    'user_id' => $user->id,
                    'booking_source' => 'claimed_guest',
                    'claimed_by_user_id' => $user->id,
                    'claimed_at' => now(),
                    'manage_token_hash' => null,
                    'manage_token_ciphertext' => null,
                ]);
                $guest->reminders()->update(['user_id' => $user->id]);
                if ($guest->status === 'reserved') {
                    $this->scheduleReminders($guest, $event);
                }

                return $guest->fresh(['event', 'user']);
            }

            $existingWasConfirmed = in_array($existing->status, ['reserved', 'attended'], true);
            $guestWasConfirmed = $guest->status === 'reserved';
            if (! in_array($existing->status, ['reserved', 'attended'], true) && $guestWasConfirmed) {
                $earliestBookedAt = $existing->booked_at->lte($guest->booked_at) ? $existing->booked_at : $guest->booked_at;
                $existing->update([
                    'status' => 'reserved', 'booked_at' => $earliestBookedAt,
                    'cancelled_at' => null, 'cancellation_reason' => null, 'booking_source' => 'claimed_guest',
                    'attendee_name' => $guest->attendee_name, 'attendee_email' => $guest->attendee_email,
                    'attendee_phone' => $guest->attendee_phone, 'registration_answers' => $guest->registration_answers,
                    'price_amount_snapshot' => $guest->price_amount_snapshot, 'currency_snapshot' => $guest->currency_snapshot,
                    'payment_note_snapshot' => $guest->payment_note_snapshot, 'claimed_at' => now(),
                ]);
            } elseif (! in_array($existing->status, ['reserved', 'waitlisted', 'attended'], true)) {
                $earliestBookedAt = $existing->booked_at->lte($guest->booked_at) ? $existing->booked_at : $guest->booked_at;
                $existing->update([
                    'status' => $guest->status, 'booked_at' => $earliestBookedAt,
                    'cancelled_at' => null, 'cancellation_reason' => null, 'booking_source' => 'claimed_guest',
                    'claimed_at' => now(),
                ]);
            }

            $guest->reminders()->whereIn('status', ['pending', 'processing'])->update(['status' => 'cancelled']);
            $guest->update([
                'status' => 'duplicate_merged', 'cancelled_at' => now(), 'claimed_by_user_id' => $user->id,
                'claimed_at' => now(), 'manage_token_hash' => null,
                'manage_token_ciphertext' => null,
            ]);
            if ($existing->fresh()->status === 'reserved') {
                $this->scheduleReminders($existing->fresh(), $event);
            }
            if ($guestWasConfirmed && $existingWasConfirmed) {
                $this->promoteWaitlist($event);
            }

            return $existing->fresh(['event', 'user']);
        });
    }

    public function cancel(User $user, Event $event): EventBooking
    {
        return DB::transaction(function () use ($user, $event): EventBooking {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            $booking = EventBooking::query()->where('event_id', $event->id)->where('user_id', $user->id)->lockForUpdate()->firstOrFail();
            $this->ensureCancellationOpen($event, $booking);
            $wasReserved = $booking->status === 'reserved';
            $booking->update(['status' => 'cancelled', 'cancelled_at' => now()]);
            $booking->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
            if ($wasReserved) {
                $this->promoteWaitlist($event);
            }
            DB::afterCommit(fn () => $this->notifier->send($user, $event, NotificationType::EventBookingCancelled->value, 'Event booking cancelled', "Your booking for {$event->title} was cancelled."));

            return $booking->fresh();
        });
    }

    public function save(User $actor, array $data, ?Event $event = null): Event
    {
        return DB::transaction(function () use ($actor, $data, $event): Event {
            if ($event?->exists) {
                $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            }
            $wasPublished = $event?->status === 'published';
            $previousHostUserId = $event?->host_user_id;
            if ($event && in_array($event->status, ['cancelled', 'completed'], true)) {
                $this->invalid('event', 'Cancelled or completed events cannot be edited.');
            }
            if ($wasPublished && isset($data['status']) && $data['status'] !== 'published') {
                $this->invalid('status', 'A published event cannot be moved back to draft. Cancel it instead.');
            }
            $timezone = (string) ($data['timezone'] ?? $event?->timezone ?? 'UTC');
            foreach (['starts_at', 'ends_at', 'booking_opens_at', 'booking_closes_at', 'cancellation_closes_at'] as $field) {
                if (! empty($data[$field])) {
                    $data[$field] = Carbon::parse($data[$field], $timezone)->utc();
                }
            }
            if ($event && array_key_exists('capacity', $data)) {
                $reserved = $event->bookings()->whereIn('status', ['reserved', 'attended'])->count();
                if ($data['capacity'] !== null && $data['capacity'] < $reserved) {
                    $this->invalid('capacity', 'Capacity cannot be lower than confirmed bookings.');
                }
            }
            if (($data['pricing_type'] ?? $event?->pricing_type) === 'free') {
                $data['price_amount'] = null;
            }
            $data['currency'] = strtoupper($data['currency'] ?? $event?->currency ?? 'INR');
            $data['waitlist_enabled'] = $data['waitlist_enabled'] ?? $event?->waitlist_enabled ?? true;
            $data['status'] = $data['status'] ?? $event?->status ?? 'draft';
            $scope = (string) ($data['scope'] ?? $event?->scope ?? 'gym');
            $data['booking_audience'] ??= $event?->booking_audience ?? ($scope === 'global' ? 'atlas_members' : 'gym_members');
            $data['app_visibility'] ??= $event?->app_visibility ?? ($scope === 'global' ? 'all_atlas' : 'hosting_gym');
            $data['public_booking_enabled'] = (bool) ($data['public_booking_enabled'] ?? $event?->public_booking_enabled ?? false);
            if ($data['public_booking_enabled'] && $data['booking_audience'] !== 'anyone') {
                $this->invalid('booking_audience', 'Public booking requires the Anyone audience.');
            }
            if ($scope === 'global' && $data['booking_audience'] === 'gym_members') {
                $this->invalid('booking_audience', 'A global event cannot be limited to a hosting gym.');
            }
            if ($scope === 'global' && $data['app_visibility'] === 'hosting_gym') {
                $this->invalid('app_visibility', 'A global event cannot use hosting-gym visibility.');
            }
            if ($scope === 'gym' && $data['app_visibility'] === 'all_atlas') {
                $this->invalid('app_visibility', 'Gym events cannot be broadcast to every Atlas member app. Share the event link instead.');
            }
            if ($data['app_visibility'] === 'all_atlas' && $data['booking_audience'] === 'gym_members') {
                $this->invalid('app_visibility', 'An event shown to all Atlas members cannot be limited to one gym.');
            }
            if ($data['app_visibility'] === 'link_only' && $data['booking_audience'] === 'gym_members') {
                $this->invalid('app_visibility', 'Link-only events must allow Atlas members or public guests with the link.');
            }
            $gymId = $data['gym_id'] ?? $event?->gym_id;
            $branchId = $data['branch_id'] ?? $event?->branch_id;
            if ($scope === 'global' && ($gymId !== null || $branchId !== null)) {
                $this->invalid('scope', 'Global events cannot have a gym or branch scope.');
            }
            if ($scope === 'gym' && ! $gymId) {
                $this->invalid('gym_id', 'A gym event requires a gym.');
            }
            if ($branchId && ! Branch::query()->whereKey($branchId)->where('gym_id', $gymId)->exists()) {
                $this->invalid('branch_id', 'The selected branch does not belong to this gym.');
            }
            $hostUserId = array_key_exists('host_user_id', $data) ? $data['host_user_id'] : $event?->host_user_id;
            if ($hostUserId) {
                $hostIsEligible = User::query()->whereKey($hostUserId)->where('is_active', true)->whereHas('roles', fn ($q) => $q->where('name', 'trainer'))
                    ->when($scope === 'gym', fn ($q) => $q->whereHas('trainerProfile', fn ($profile) => $profile
                        ->where('gym_id', $gymId)->where('is_active', true)->where('status', 'active')))->exists();
                if (! $hostIsEligible) {
                    $this->invalid('host_user_id', 'Select an active trainer in the event scope.');
                }
            }
            $startsAt = $data['starts_at'] ?? $event?->starts_at;
            if ($data['status'] === 'published' && $startsAt && Carbon::parse($startsAt)->lte(now())) {
                $this->invalid('starts_at', 'A published event must start in the future.');
            }
            if ($data['status'] === 'published' && ! $event?->published_at) {
                $data['published_at'] = now();
            }
            if (! $event) {
                $data['created_by_user_id'] = $actor->id;
            }
            $event ??= new Event;
            $event->fill($data)->save();
            if ($event->wasChanged('capacity')) {
                $this->fillAvailableSpots($event);
            }
            $hostChanged = $previousHostUserId !== $event->host_user_id;
            $materiallyChanged = $event->wasChanged(['starts_at', 'ends_at', 'location_name', 'address', 'latitude', 'longitude']);
            if (! $wasPublished && $event->status === 'published') {
                SendEventPublishedNotifications::dispatch($event->id);
                if (! $hostChanged) {
                    $this->notifyHostAfterCommit($event, NotificationType::EventPublished->value, 'You are hosting an event', "You are listed as the host for {$event->title}.");
                }
            }
            if ($hostChanged) {
                $this->notifyHostAfterCommit($event, NotificationType::EventUpdated->value, 'You were assigned as event host', "Your gym assigned you to host {$event->title}. Open Events to manage it.");
                if ($previousHostUserId) {
                    $this->notifySpecificHostAfterCommit($previousHostUserId, $event, 'Event host assignment changed', "You are no longer the host for {$event->title}.");
                }
            }
            if ($event->wasChanged(['starts_at', 'ends_at'])) {
                foreach ($event->bookings()->where('status', 'reserved')->get() as $booking) {
                    $this->scheduleReminders($booking, $event);
                }
            }
            if ($wasPublished && $materiallyChanged) {
                SendEventBookingAudienceNotification::dispatch(
                    $event->id,
                    NotificationType::EventUpdated->value,
                    'Event details updated',
                    "The schedule or location for {$event->title} has changed. Open the event to review it.",
                );
                if ($event->host_user_id !== $actor->id) {
                    $this->notifyHostAfterCommit($event, NotificationType::EventUpdated->value, 'Hosted event updated', "The schedule or location for {$event->title} has changed.");
                }
            }

            return $event->fresh(['gym', 'branch', 'host']);
        });
    }

    public function cancelEvent(Event $event, string $reason): Event
    {
        DB::transaction(function () use ($event, $reason): void {
            $event = Event::query()->lockForUpdate()->findOrFail($event->id);
            if (! in_array($event->status, ['draft', 'published'], true)) {
                $this->invalid('event', 'Only draft or published events can be cancelled.');
            }
            $event->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancellation_reason' => $reason]);
            EventReminder::query()->where('event_id', $event->id)->where('status', 'pending')->update(['status' => 'cancelled']);
            $event->bookings()->whereIn('status', ['reserved', 'waitlisted'])->update(['status' => 'event_cancelled']);
            SendEventBookingAudienceNotification::dispatch(
                $event->id,
                NotificationType::EventCancelled->value,
                'Event cancelled',
                "{$event->title} has been cancelled. {$reason}",
                ['event_cancelled'],
            );
            $this->notifyHostAfterCommit($event, NotificationType::EventCancelled->value, 'Hosted event cancelled', "{$event->title} has been cancelled. {$reason}");
        });

        return $event->fresh();
    }

    public function checkIn(User $actor, EventBooking $booking, bool $noShow = false): EventBooking
    {
        $event = $booking->event()->firstOrFail();
        if (! in_array($event->status, ['published', 'completed'], true)) {
            $this->invalid('booking', 'Attendance is not available for this event.');
        }
        if (now()->lt($event->starts_at->copy()->subHours(2))) {
            $this->invalid('booking', 'Attendance opens two hours before the event starts.');
        }
        if (now()->gt($event->ends_at->copy()->addDay())) {
            $this->invalid('booking', 'Attendance is closed for this event.');
        }
        if (! in_array($booking->status, ['reserved', 'attended', 'no_show'], true)) {
            $this->invalid('booking', 'Only confirmed attendees can be checked in.');
        }
        $booking->update($noShow ? ['status' => 'no_show', 'checked_in_at' => null, 'checked_in_by_user_id' => $actor->id]
            : ['status' => 'attended', 'checked_in_at' => now(), 'checked_in_by_user_id' => $actor->id]);

        return $booking->fresh(['user']);
    }

    public function runDueReminders(): int
    {
        $sent = 0;
        EventReminder::query()->where('status', 'processing')->where('updated_at', '<=', now()->subMinutes(10))->update(['status' => 'pending']);
        EventReminder::query()->where('status', 'pending')->where('scheduled_for', '<=', now())->with(['booking', 'event', 'user'])
            ->chunkById(100, function ($reminders) use (&$sent): void {
                foreach ($reminders as $reminder) {
                    $claimed = EventReminder::query()->whereKey($reminder->id)->where('status', 'pending')->update(['status' => 'processing']);
                    if ($claimed !== 1) {
                        continue;
                    }
                    $reminder->refresh()->load(['booking', 'event', 'user']);
                    if ($reminder->event->status !== 'published' || $reminder->booking->status !== 'reserved') {
                        $reminder->update(['status' => 'cancelled']);

                        continue;
                    }
                    try {
                        $this->notifier->sendBooking($reminder->booking, $reminder->event, NotificationType::EventReminder->value, 'Upcoming event reminder', "{$reminder->event->title} starts {$reminder->event->starts_at->diffForHumans()}.");
                        $reminder->update(['status' => 'sent', 'sent_at' => now()]);
                        $sent++;
                    } catch (\Throwable $exception) {
                        $reminder->update(['status' => 'pending']);
                        report($exception);
                    }
                }
            });
        Event::query()->where('status', 'published')->where('ends_at', '<', now())->chunkById(100, function ($events): void {
            foreach ($events as $event) {
                DB::transaction(function () use ($event): void {
                    $locked = Event::query()->lockForUpdate()->find($event->id);
                    if (! $locked || $locked->status !== 'published' || $locked->ends_at->isFuture()) {
                        return;
                    }
                    $locked->bookings()->where('status', 'reserved')->update(['status' => 'no_show']);
                    $locked->bookings()->where('status', 'waitlisted')->update(['status' => 'waitlist_expired']);
                    $locked->reminders()->where('status', 'pending')->update(['status' => 'cancelled']);
                    $locked->update(['status' => 'completed']);
                    $this->audit->log('system.event.completed', 'update', subject: $locked, gym: $locked->gym, branch: $locked->branch, newValues: ['status' => 'completed']);
                });
            }
        });

        return $sent;
    }

    private function baseUpcomingQuery(User $user): Builder
    {
        return Event::query()->with(['gym:id,name', 'branch:id,name', 'host:id,name,avatar', 'bookings' => fn ($q) => $q->where('user_id', $user->id)])
            ->withCount(['bookings as reserved_count' => fn ($q) => $q->whereIn('status', ['reserved', 'attended'])])
            ->where('status', 'published')->where('ends_at', '>=', now())->orderBy('starts_at');
    }

    private function scopeOperationalEvents(Builder $query): Builder
    {
        return $query->where(function (Builder $scope): void {
            $scope->where('scope', 'global')->orWhere(function (Builder $gymEvent): void {
                $gymEvent->where('scope', 'gym')
                    ->whereHas('gym', fn (Builder $gym) => $gym
                        ->where('is_active', true)
                        ->where('status', 'active')
                        ->where('operational_access_enabled', true))
                    ->where(function (Builder $branch): void {
                        $branch->whereNull('branch_id')->orWhereHas('branch', fn (Builder $activeBranch) => $activeBranch
                            ->where('is_active', true)
                            ->where('status', 'active'));
                    });
            });
        });
    }

    private function scheduleReminders(EventBooking $booking, Event $event): void
    {
        foreach (['24h' => $event->starts_at->copy()->subDay(), '1h' => $event->starts_at->copy()->subHour()] as $type => $when) {
            EventReminder::query()->updateOrCreate(['event_booking_id' => $booking->id, 'type' => $type],
                ['event_id' => $event->id, 'user_id' => $booking->user_id, 'scheduled_for' => $when, 'status' => $when->isFuture() ? 'pending' : 'cancelled', 'sent_at' => null]);
        }
    }

    private function ensureBookingOpen(Event $event): void
    {
        if ($event->status !== 'published' || $event->starts_at->isPast()
            || ($event->booking_opens_at && now()->lt($event->booking_opens_at))
            || ($event->booking_closes_at && now()->gt($event->booking_closes_at))) {
            $this->invalid('event', 'Booking is not open for this event.');
        }
    }

    private function ensureEventOperational(Event $event): void
    {
        $query = Event::query()->whereKey($event->id);
        $this->scopeOperationalEvents($query);
        if (! $query->exists()) {
            $this->invalid('event', 'This event is not currently available.');
        }
    }

    private function ensureCancellationOpen(Event $event, EventBooking $booking): void
    {
        if ($event->status !== 'published' || $event->starts_at->isPast()) {
            $this->invalid('booking', 'This event is no longer open for booking changes.');
        }
        if ($event->cancellation_closes_at && now()->gt($event->cancellation_closes_at)) {
            $this->invalid('booking', 'The cancellation window has closed.');
        }
        if (! in_array($booking->status, ['reserved', 'waitlisted'], true)) {
            $this->invalid('booking', 'This booking cannot be cancelled.');
        }
    }

    private function nextBookingStatus(Event $event): string
    {
        $reserved = EventBooking::query()->where('event_id', $event->id)->whereIn('status', ['reserved', 'attended'])->count();
        $status = $event->capacity === null || $reserved < $event->capacity ? 'reserved' : 'waitlisted';
        if ($status === 'waitlisted' && ! $event->waitlist_enabled) {
            $this->invalid('event', 'This event is full.');
        }

        return $status;
    }

    private function promoteWaitlist(Event $event): void
    {
        $next = EventBooking::query()->where('event_id', $event->id)->where('status', 'waitlisted')->orderBy('booked_at')->orderBy('id')->lockForUpdate()->first();
        if (! $next) {
            return;
        }
        $next->update(['status' => 'reserved', 'promoted_at' => now()]);
        $this->scheduleReminders($next, $event);
        DB::afterCommit(fn () => $this->notifier->sendBooking(
            $next->fresh(['user']), $event, NotificationType::EventWaitlistPromoted->value,
            'Your event spot is confirmed', "A spot opened for {$event->title}. Your booking is now confirmed.",
        ));
    }

    private function fillAvailableSpots(Event $event): void
    {
        $reserved = $event->bookings()->whereIn('status', ['reserved', 'attended'])->count();
        while ($event->capacity === null || $reserved < $event->capacity) {
            $waiting = $event->bookings()->where('status', 'waitlisted')->exists();
            if (! $waiting) {
                return;
            }
            $this->promoteWaitlist($event);
            $reserved++;
        }
    }

    private function notifyHostAfterCommit(Event $event, string $type, string $title, string $body): void
    {
        if (! $event->host_user_id) {
            return;
        }
        $host = User::query()->find($event->host_user_id);
        if ($host) {
            DB::afterCommit(fn () => $this->notifier->send($host, $event, $type, $title, $body, 'trainer'));
        }
    }

    private function notifySpecificHostAfterCommit(int $userId, Event $event, string $title, string $body): void
    {
        $host = User::query()->find($userId);
        if ($host) {
            DB::afterCommit(fn () => $this->notifier->send($host, $event, NotificationType::EventUpdated->value, $title, $body, 'trainer'));
        }
    }

    private function invalid(string $key, string $message): never
    {
        throw ValidationException::withMessages([$key => [$message]]);
    }
}
