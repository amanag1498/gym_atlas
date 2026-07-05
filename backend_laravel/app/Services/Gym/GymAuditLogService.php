<?php

namespace App\Services\Gym;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Gym;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class GymAuditLogService
{
    /**
     * @param  list<int>  $accessibleBranchIds
     * @return array{actor:?string,action:?string,subject_type:?string,branch_id:?int,start_date:?Carbon,end_date:?Carbon}
     */
    public function parseFilters(Request $request, array $accessibleBranchIds): array
    {
        $branchId = $request->filled('branch_id') ? (int) $request->integer('branch_id') : null;
        if ($branchId !== null && ! in_array($branchId, $accessibleBranchIds, true)) {
            $branchId = null;
        }

        return [
            'actor' => $request->string('actor')->trim()->toString() ?: null,
            'action' => $request->string('action')->trim()->toString() ?: null,
            'subject_type' => $request->string('subject_type')->trim()->toString() ?: null,
            'branch_id' => $branchId,
            'start_date' => $request->date('start_date')?->startOfDay(),
            'end_date' => $request->date('end_date')?->endOfDay(),
        ];
    }

    /**
     * @param  array{actor:?string,action:?string,subject_type:?string,branch_id:?int,start_date:?Carbon,end_date:?Carbon}  $filters
     * @param  list<int>  $accessibleBranchIds
     */
    public function query(Gym $gym, array $filters, array $accessibleBranchIds): Builder
    {
        $branchIds = $filters['branch_id'] ? [$filters['branch_id']] : $accessibleBranchIds;

        $query = ActivityLog::query()
            ->with([
                'actor:id,name,email',
                'gym:id,name',
                'branch:id,name',
            ])
            ->where('gym_id', $gym->id)
            ->where(function (Builder $builder) use ($branchIds): void {
                $builder->whereNull('branch_id')
                    ->orWhereIn('branch_id', $branchIds);
            })
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
     * @return array<int, string>
     */
    public function subjectTypeOptions(Gym $gym, array $accessibleBranchIds): array
    {
        return ActivityLog::query()
            ->where('gym_id', $gym->id)
            ->where(function (Builder $builder) use ($accessibleBranchIds): void {
                $builder->whereNull('branch_id')
                    ->orWhereIn('branch_id', $accessibleBranchIds);
            })
            ->whereNotNull('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->pluck('subject_type')
            ->values()
            ->all();
    }

    /**
     * @return \Illuminate\Database\Eloquent\Collection<int, Branch>
     */
    public function branchOptions(Gym $gym, array $accessibleBranchIds)
    {
        return Branch::query()
            ->where('gym_id', $gym->id)
            ->whereIn('id', $accessibleBranchIds)
            ->orderBy('name')
            ->get(['id', 'name']);
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
