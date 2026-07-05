<?php

namespace App\Services\Web;

use App\Models\Branch;
use App\Models\Gym;
use App\Models\MembershipPlan;
use App\Models\TrainerProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GymMemberImportService
{
    /**
     * @return array{
     *     token:string,
     *     rows:list<array<string,mixed>>,
     *     ready_rows:list<array<string,mixed>>,
     *     summary:array{total:int,ready:int,duplicates:int,errors:int,warnings:int}
     * }
     */
    public function preview(UploadedFile $file, Gym $gym, Collection $branches, Collection $trainers, Collection $plans): array
    {
        $headers = [];
        $rows = [];
        $readyRows = [];
        $warningsCount = 0;

        foreach ($this->readCsv($file) as $index => $row) {
            if ($index === 0) {
                $headers = $this->normalizeHeaders($row);
                continue;
            }

            if ($this->rowIsBlank($row)) {
                continue;
            }

            $mapped = $this->mapRow($headers, $row);
            $prepared = $this->prepareRow($mapped, $gym, $branches, $trainers, $plans, count($rows) + 2);
            $rows[] = $prepared;

            if ($prepared['status'] === 'ready') {
                $readyRows[] = $prepared['normalized'];
            }

            $warningsCount += count($prepared['warnings']);
        }

        return [
            'token' => (string) Str::uuid(),
            'rows' => $rows,
            'ready_rows' => $readyRows,
            'summary' => [
                'total' => count($rows),
                'ready' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'ready')),
                'duplicates' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'duplicate')),
                'errors' => count(array_filter($rows, fn (array $row): bool => $row['status'] === 'error')),
                'warnings' => $warningsCount,
            ],
        ];
    }

    /**
     * @return \Generator<int, array<int, string|null>>
     */
    private function readCsv(UploadedFile $file): \Generator
    {
        $csv = fopen($file->getRealPath(), 'r');

        while (($row = fgetcsv($csv)) !== false) {
            yield $row;
        }

        fclose($csv);
    }

    /**
     * @param  array<int, string|null>  $headers
     * @return list<string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(function ($header): string {
            $normalized = Str::of((string) $header)->lower()->trim()->replace([' ', '-'], '_')->toString();

            return match ($normalized) {
                'fitness_goal' => 'goal',
                'membership_plan_name', 'plan', 'membership' => 'membership_plan',
                default => $normalized,
            };
        }, $headers);
    }

    /**
     * @param  list<string>  $headers
     * @param  array<int, string|null>  $row
     * @return array<string, string|null>
     */
    private function mapRow(array $headers, array $row): array
    {
        $mapped = [];

        foreach ($headers as $index => $header) {
            if ($header === '') {
                continue;
            }

            $mapped[$header] = isset($row[$index]) ? trim((string) $row[$index]) : null;
        }

        return $mapped;
    }

    /**
     * @param  array<string, string|null>  $mapped
     * @return array<string, mixed>
     */
    private function prepareRow(array $mapped, Gym $gym, Collection $branches, Collection $trainers, Collection $plans, int $rowNumber): array
    {
        $warnings = [];
        $errors = [];

        $validator = Validator::make($mapped, [
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'gender' => ['nullable', 'string', 'max:40'],
            'goal' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:160'],
            'membership_plan' => ['nullable', 'string', 'max:160'],
            'start_date' => ['nullable', 'date'],
            'trainer' => ['nullable', 'string', 'max:160'],
        ]);

        if ($validator->fails()) {
            $errors = $validator->errors()->all();
        }

        $branch = $this->resolveBranch($mapped['branch'] ?? null, $branches);
        $trainer = $this->resolveTrainer($mapped['trainer'] ?? null, $trainers, $branch?->id);
        $plan = $this->resolvePlan($mapped['membership_plan'] ?? null, $plans, $branch?->id);

        if (($mapped['branch'] ?? null) && ! $branch) {
            $errors[] = 'Branch was not found in the current gym scope.';
        }

        if (($mapped['trainer'] ?? null) && ! $trainer) {
            $errors[] = 'Trainer was not found in the current gym scope.';
        }

        if (($mapped['membership_plan'] ?? null) && ! $plan) {
            $errors[] = 'Membership plan was not found in the current gym scope.';
        }

        $existingInGym = User::query()
            ->where('email', $mapped['email'] ?? '')
            ->whereHas('memberProfile', fn ($query) => $query->where('gym_id', $gym->id))
            ->exists();

        $normalized = [
            'name' => $mapped['name'] ?? null,
            'email' => Str::lower((string) ($mapped['email'] ?? '')),
            'emergency_contact_phone' => $mapped['phone'] ?: null,
            'fitness_goal' => $mapped['goal'] ?: null,
            'gender' => ($mapped['gender'] ?? null) ?: null,
            'branch_id' => $branch?->id ?? ($branches->count() === 1 ? $branches->first()?->id : null),
            'assigned_trainer_user_id' => $trainer?->id,
            'membership_plan_id' => $plan?->id,
            'membership_plan_name' => $plan?->name,
            'start_date' => $mapped['start_date'] ?: now()->toDateString(),
        ];

        if ($normalized['membership_plan_id'] !== null && $normalized['branch_id'] === null) {
            $errors[] = 'A branch is required before importing a membership assignment.';
        }

        $status = 'ready';

        if ($existingInGym) {
            $status = 'duplicate';
            $errors[] = 'A member with this email already exists in the current gym.';
        } elseif ($errors !== []) {
            $status = 'error';
        }

        return [
            'row_number' => $rowNumber,
            'status' => $status,
            'input' => $mapped,
            'normalized' => $normalized,
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function rowIsBlank(array $row): bool
    {
        return collect($row)->every(fn ($value): bool => blank(trim((string) $value)));
    }

    private function resolveBranch(?string $branch, Collection $branches): ?Branch
    {
        if (blank($branch)) {
            return null;
        }

        $needle = Str::lower(trim($branch));

        return $branches->first(function (Branch $item) use ($needle): bool {
            return Str::lower($item->name) === $needle || Str::lower($item->slug) === $needle;
        });
    }

    private function resolveTrainer(?string $trainer, Collection $trainers, ?int $branchId): ?User
    {
        if (blank($trainer)) {
            return null;
        }

        $needle = Str::lower(trim($trainer));

        return $trainers->first(function (User $user) use ($needle, $branchId): bool {
            $matches = Str::lower($user->name) === $needle || Str::lower((string) $user->email) === $needle;
            $trainerBranchId = $user->managedTrainerProfile?->branch_id;

            if (! $matches) {
                return false;
            }

            return $branchId === null || $trainerBranchId === null || (int) $trainerBranchId === $branchId;
        });
    }

    private function resolvePlan(?string $plan, Collection $plans, ?int $branchId): ?MembershipPlan
    {
        if (blank($plan)) {
            return null;
        }

        $needle = Str::lower(trim($plan));

        return $plans->first(function (MembershipPlan $item) use ($needle, $branchId): bool {
            if (Str::lower($item->name) !== $needle) {
                return false;
            }

            return $item->branch_id === null || $branchId === null || (int) $item->branch_id === $branchId;
        });
    }
}
