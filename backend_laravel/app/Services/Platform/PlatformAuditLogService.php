<?php

namespace App\Services\Platform;

use App\Models\ActivityLog;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class PlatformAuditLogService
{
    private const IGNORED_DIFF_KEYS = [
        'id',
        'created_at',
        'updated_at',
        'deleted_at',
        'pivot',
        'password',
        'remember_token',
        'email_verified_at',
        'google_id',
        'firebase_uid',
        'plan_snapshot',
    ];

    /**
     * @return array{actor:?string,action:?string,subject_type:?string,gym_id:?int,start_date:?Carbon,end_date:?Carbon}
     */
    public function parseFilters(Request $request): array
    {
        return [
            'actor' => $request->string('actor')->trim()->toString() ?: null,
            'action' => $request->string('action')->trim()->toString() ?: null,
            'subject_type' => $request->string('subject_type')->trim()->toString() ?: null,
            'gym_id' => $request->filled('gym') ? (int) $request->integer('gym') : null,
            'start_date' => $request->date('start_date')?->startOfDay(),
            'end_date' => $request->date('end_date')?->endOfDay(),
        ];
    }

    /**
     * @param  array{actor:?string,action:?string,subject_type:?string,gym_id:?int,start_date:?Carbon,end_date:?Carbon}  $filters
     */
    public function query(array $filters): Builder
    {
        $query = ActivityLog::query()
            ->with([
                'actor:id,name,email',
                'gym:id,name',
                'branch:id,name',
            ])
            ->latest('occurred_at')
            ->latest('id');

        if ($filters['actor']) {
            $search = '%'.$filters['actor'].'%';
            $query->whereHas('actor', function (Builder $builder) use ($search): void {
                $builder->where('name', 'like', $search)
                    ->orWhere('email', 'like', $search);
            });
        }

        if ($filters['action']) {
            $action = '%'.$filters['action'].'%';
            $query->where(function (Builder $builder) use ($action): void {
                $builder->where('action', 'like', $action)
                    ->orWhere('event', 'like', $action);
            });
        }

        if ($filters['subject_type']) {
            $query->where('subject_type', $filters['subject_type']);
        }

        if ($filters['gym_id']) {
            $query->where('gym_id', $filters['gym_id']);
        }

        if ($filters['start_date']) {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->where('occurred_at', '>=', $filters['start_date'])
                    ->orWhere(function (Builder $nested) use ($filters): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '>=', $filters['start_date']);
                    });
            });
        }

        if ($filters['end_date']) {
            $query->where(function (Builder $builder) use ($filters): void {
                $builder->where('occurred_at', '<=', $filters['end_date'])
                    ->orWhere(function (Builder $nested) use ($filters): void {
                        $nested->whereNull('occurred_at')
                            ->where('created_at', '<=', $filters['end_date']);
                    });
            });
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    public function sanitizeValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $sanitized = [];

            foreach ($value as $key => $nestedValue) {
                if (is_string($key) && $this->isSensitiveKey($key)) {
                    $sanitized[$key] = '[redacted]';
                    continue;
                }

                $sanitized[$key] = $this->sanitizeValue($nestedValue);
            }

            return $sanitized;
        }

        if (is_object($value)) {
            return $this->sanitizeValue((array) $value);
        }

        return $value;
    }

    /**
     * @return array<int, string>
     */
    public function subjectTypeOptions(): array
    {
        return ActivityLog::query()
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Gym>
     */
    public function gymOptions()
    {
        return Gym::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    /**
     * @param  iterable<ActivityLog>  $logs
     * @return list<array<string, mixed>>
     */
    public function presentLogs(iterable $logs): array
    {
        $items = [];

        foreach ($logs as $log) {
            $items[] = [
                'id' => $log->id,
                'title' => $this->titleFor($log),
                'description' => $this->descriptionFor($log),
                'subject_name' => $this->resolveSubjectName($log),
                'subject_label' => $this->subjectLabel($log),
                'actor_name' => $log->actor?->name ?? 'System',
                'actor_email' => $log->actor?->email,
                'actor_role' => $this->formatRole($log->actor_role),
                'action_label' => $this->actionLabel($log),
                'icon' => $this->iconFor($log),
                'tone' => $this->toneFor($log),
                'gym_name' => $log->gym?->name ?? 'Platform-wide',
                'branch_name' => $log->branch?->name,
                'ip_address' => $log->ip_address ?: 'N/A',
                'user_agent' => $log->user_agent ?: 'No user agent',
                'occurred_at' => ($log->occurred_at ?? $log->created_at)?->format('d M Y, h:i A') ?? 'N/A',
                'relative_time' => ($log->occurred_at ?? $log->created_at)?->diffForHumans(),
                'changes' => $this->changeRows($log),
                'old_values' => $this->sanitizeValue($log->old_values ?? []),
                'new_values' => $this->sanitizeValue($log->new_values ?? []),
                'context' => $this->sanitizeValue($log->context ?? []),
            ];
        }

        return $items;
    }

    /**
     * @param  iterable<ActivityLog>  $logs
     * @return array<string, int>
     */
    public function summarizeLogs(iterable $logs): array
    {
        $collection = Collection::make($logs);

        return [
            'visible' => $collection->count(),
            'created' => $collection->filter(fn (ActivityLog $log): bool => $log->action === 'create')->count(),
            'updated' => $collection->filter(fn (ActivityLog $log): bool => $log->action === 'update')->count(),
            'deleted' => $collection->filter(fn (ActivityLog $log): bool => $log->action === 'delete')->count(),
            'system' => $collection->filter(fn (ActivityLog $log): bool => $log->actor === null)->count(),
        ];
    }

    public function formatRole(?string $role): string
    {
        return $role ? Str::of($role)->replace(['_', '.'], ' ')->title()->toString() : 'System';
    }

    private function titleFor(ActivityLog $log): string
    {
        return match ($log->event) {
            'platform_admin_created_gym' => 'Gym onboarded',
            'platform_admin_updated_gym' => 'Gym profile updated',
            'platform.gym.approval.updated', 'web.platform.gym.approval.updated' => 'Approval status changed',
            'platform.gym.status.updated' => 'Gym status changed',
            'platform.gym.verification.updated' => 'Verification status changed',
            'platform.gym.listing_flags.updated', 'web.platform.gym.featured.updated', 'web.platform.gym.promoted.updated', 'web.platform.gym.listing.updated', 'platform.gym.public_listing.updated', 'platform.gym.public_listing.visibility_updated' => 'Listing visibility updated',
            'platform.settings.updated' => 'Platform settings updated',
            'platform.facility.created', 'web.platform.facility.created' => 'Facility added',
            'platform.facility.updated', 'web.platform.facility.updated' => 'Facility updated',
            'platform.facility.status_toggled', 'web.platform.facility.status_toggled' => 'Facility status changed',
            'platform.facility.deleted', 'web.platform.facility.deleted' => 'Facility removed',
            'platform.city.created' => 'City added',
            'platform.city.updated' => 'City updated',
            'platform.city.deleted' => 'City removed',
            'platform.fitness_goal.created' => 'Fitness goal added',
            'platform.fitness_goal.updated', 'platform.fitness_goal.status.updated' => 'Fitness goal updated',
            'platform.fitness_goal.deleted' => 'Fitness goal removed',
            'platform.trainer_specialization.created' => 'Trainer specialization added',
            'platform.trainer_specialization.updated', 'platform.trainer_specialization.status.updated' => 'Trainer specialization updated',
            'platform.trainer_specialization.deleted' => 'Trainer specialization removed',
            'platform.banner.created' => 'Banner created',
            'platform.banner.updated' => 'Banner updated',
            'platform.banner.deleted' => 'Banner removed',
            'platform.workout_book.created', 'web.platform.workout_book.created' => 'Workout book created',
            'platform.workout_book.updated', 'web.platform.workout_book.updated' => 'Workout book updated',
            'platform.workout_book.deleted', 'web.platform.workout_book.deleted' => 'Workout book removed',
            'platform.gym_owner.created' => 'Gym owner added',
            'platform.gym_owner.updated' => 'Gym owner profile updated',
            'platform.gym_owner.activated', 'platform.user.activated' => 'Account activated',
            'platform.gym_owner.deactivated', 'platform.user.deactivated' => 'Account deactivated',
            'web.platform.subscription-plan.created' => 'Platform plan created',
            'web.platform.subscription-plan.updated' => 'Platform plan updated',
            'web.platform.gym-subscription.created' => 'Gym subscription assigned',
            'web.platform.gym-subscription.updated' => 'Gym subscription updated',
            'web.platform.gym-subscription.renewed' => 'Gym subscription renewed',
            'web.platform.gym-subscription.invoice-paid' => 'Platform invoice marked paid',
            default => Str::of($log->event ?: ($log->action ?: 'activity'))
                ->replace(['.', '_', '-'], ' ')
                ->title()
                ->toString(),
        };
    }

    private function descriptionFor(ActivityLog $log): string
    {
        $subject = $this->resolveSubjectName($log);
        $new = $this->sanitizeValue($log->new_values ?? []);

        return match ($log->event) {
            'platform_admin_created_gym' => "{$subject} was added to the platform directory.",
            'platform_admin_updated_gym' => "{$subject} details were refreshed for the platform team.",
            'platform.gym.approval.updated', 'web.platform.gym.approval.updated' => "{$subject} moved to ".$this->humanizeValue($new['approval_status'] ?? 'updated').".",
            'platform.gym.status.updated' => "{$subject} is now ".$this->humanizeValue($new['status'] ?? 'updated').".",
            'platform.gym.verification.updated' => "{$subject} verification is now ".$this->humanizeValue($new['verification_status'] ?? 'updated').".",
            'platform.gym.listing_flags.updated', 'web.platform.gym.featured.updated', 'web.platform.gym.promoted.updated', 'web.platform.gym.listing.updated', 'platform.gym.public_listing.updated', 'platform.gym.public_listing.visibility_updated' => $this->listingSummary($log, $subject),
            'platform.settings.updated' => 'Core platform controls, notification rules, or defaults were changed.',
            'web.platform.gym-subscription.created' => "{$subject} received a new platform billing assignment.",
            'web.platform.gym-subscription.updated' => "{$subject} billing terms or dates were updated.",
            'web.platform.gym-subscription.renewed' => "{$subject} billing cycle was renewed for the next term.",
            'web.platform.gym-subscription.invoice-paid' => 'A platform invoice payment was confirmed and recorded.',
            default => "{$subject} was affected by this admin action.",
        };
    }

    /**
     * @return list<array{label:string,old:?string,new:?string}>
     */
    private function changeRows(ActivityLog $log): array
    {
        $old = $this->flattenForDiff($this->sanitizeValue($log->old_values ?? []));
        $new = $this->flattenForDiff($this->sanitizeValue($log->new_values ?? []));
        $keys = array_values(array_unique(array_merge(array_keys($old), array_keys($new))));
        $rows = [];

        foreach ($keys as $key) {
            $oldValue = $old[$key] ?? null;
            $newValue = $new[$key] ?? null;

            if ($oldValue === $newValue) {
                continue;
            }

            $rows[] = [
                'label' => $this->labelForDiffKey($key),
                'old' => $oldValue,
                'new' => $newValue,
            ];

            if (count($rows) === 6) {
                break;
            }
        }

        if ($rows !== []) {
            return $rows;
        }

        $context = $this->flattenForDiff($this->sanitizeValue($log->context ?? []));

        foreach ($context as $key => $value) {
            $rows[] = [
                'label' => $this->labelForDiffKey($key),
                'old' => null,
                'new' => $value,
            ];

            if (count($rows) === 4) {
                break;
            }
        }

        return $rows;
    }

    private function actionLabel(ActivityLog $log): string
    {
        return match ($log->action) {
            'create' => 'Created',
            'update' => 'Updated',
            'delete' => 'Deleted',
            default => Str::of($log->action ?: 'activity')->headline()->toString(),
        };
    }

    private function resolveSubjectName(ActivityLog $log): string
    {
        return data_get($log->new_values, 'name')
            ?? data_get($log->old_values, 'name')
            ?? data_get($log->context, 'name')
            ?? data_get($log->new_values, 'title')
            ?? data_get($log->old_values, 'title')
            ?? data_get($log->new_values, 'invoice_number')
            ?? data_get($log->old_values, 'invoice_number')
            ?? data_get($log->new_values, 'slug')
            ?? data_get($log->old_values, 'slug')
            ?? $log->gym?->name
            ?? class_basename((string) $log->subject_type ?: 'Platform Item');
    }

    private function subjectLabel(ActivityLog $log): string
    {
        return $log->subject_type
            ? Str::headline(class_basename($log->subject_type))
            : 'Platform Entity';
    }

    private function listingSummary(ActivityLog $log, string $subject): string
    {
        $new = $this->sanitizeValue($log->new_values ?? []);
        $parts = collect([
            array_key_exists('is_featured', $new) ? ('Featured: '.$this->humanizeValue($new['is_featured'])) : null,
            array_key_exists('is_promoted', $new) ? ('Promoted: '.$this->humanizeValue($new['is_promoted'])) : null,
            array_key_exists('listing_status', $new) ? ('Status: '.$this->humanizeValue($new['listing_status'])) : null,
            array_key_exists('public_listing_approval_status', $new) ? ('Approval: '.$this->humanizeValue($new['public_listing_approval_status'])) : null,
            array_key_exists('is_public', $new) ? ('Visible: '.$this->humanizeValue($new['is_public'])) : null,
        ])->filter()->values();

        if ($parts->isEmpty()) {
            return "{$subject} listing visibility rules were changed.";
        }

        return "{$subject} listing updated. ".$parts->implode(' • ').'.';
    }

    private function toneFor(ActivityLog $log): string
    {
        return match ($log->action) {
            'create' => 'success',
            'delete' => 'danger',
            default => match ($log->event) {
                'platform.gym.approval.updated', 'platform.gym.status.updated', 'platform.gym.verification.updated', 'web.platform.gym-subscription.updated', 'web.platform.gym-subscription.renewed' => 'info',
                'web.platform.gym-subscription.invoice-paid' => 'success',
                default => 'neutral',
            },
        };
    }

    private function iconFor(ActivityLog $log): string
    {
        return match (true) {
            Str::contains($log->event, ['gym-subscription', 'subscription-plan', 'invoice']) => 'ti-receipt-2',
            Str::contains($log->event, ['gym_owner', 'platform.user']) => 'ti-user-shield',
            Str::contains($log->event, ['facility']) => 'ti-building-estate',
            Str::contains($log->event, ['city']) => 'ti-map-pin',
            Str::contains($log->event, ['fitness_goal']) => 'ti-target-arrow',
            Str::contains($log->event, ['trainer_specialization']) => 'ti-barbell',
            Str::contains($log->event, ['banner']) => 'ti-photo',
            Str::contains($log->event, ['workout_book']) => 'ti-book-2',
            Str::contains($log->event, ['settings']) => 'ti-settings',
            Str::contains($log->event, ['listing']) => 'ti-world',
            Str::contains($log->event, ['gym']) => 'ti-building-community',
            default => 'ti-history',
        };
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, string>
     */
    private function flattenForDiff(array $values, string $prefix = ''): array
    {
        $flattened = [];

        foreach ($values as $key => $value) {
            if (! is_string($key) && ! is_int($key)) {
                continue;
            }

            $segment = (string) $key;

            if ($this->shouldIgnoreDiffKey($segment)) {
                continue;
            }

            $path = $prefix === '' ? $segment : $prefix.'.'.$segment;

            if (is_array($value)) {
                if ($value === [] || Arr::isList($value)) {
                    $formattedList = collect($value)
                        ->map(fn ($item) => $this->formatScalarValue($item))
                        ->filter(fn (?string $item): bool => $item !== null && $item !== '')
                        ->implode(', ');

                    if ($formattedList !== '') {
                        $flattened[$path] = $formattedList;
                    }

                    continue;
                }

                $flattened += $this->flattenForDiff($value, $path);
                continue;
            }

            $formatted = $this->formatScalarValue($value);

            if ($formatted !== null) {
                $flattened[$path] = $formatted;
            }
        }

        return $flattened;
    }

    private function shouldIgnoreDiffKey(string $key): bool
    {
        $normalized = Str::snake($key);

        return in_array($normalized, self::IGNORED_DIFF_KEYS, true) || $this->isSensitiveKey($normalized);
    }

    private function labelForDiffKey(string $key): string
    {
        return Str::of($key)
            ->replace(['.name', '.title', '.status'], ['', '', ''])
            ->replace('.', ' ')
            ->replace('_', ' ')
            ->headline()
            ->toString();
    }

    private function formatScalarValue(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y, h:i A');
        }

        if (is_scalar($value)) {
            return $this->humanizeValue($value);
        }

        return null;
    }

    private function humanizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof Carbon) {
            return $value->format('d M Y, h:i A');
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return Str::of((string) $value)
            ->replace('_', ' ')
            ->trim()
            ->headline()
            ->toString();
    }

    private function isSensitiveKey(string $key): bool
    {
        $normalized = Str::lower($key);

        foreach (['password', 'token', 'secret', 'remember_token', 'authorization', 'cookie'] as $sensitive) {
            if (Str::contains($normalized, $sensitive)) {
                return true;
            }
        }

        return false;
    }
}
