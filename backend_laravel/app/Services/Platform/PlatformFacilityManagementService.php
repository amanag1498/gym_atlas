<?php

namespace App\Services\Platform;

use App\Models\Facility;
use App\Services\Audit\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PlatformFacilityManagementService
{
    public function __construct(
        private readonly AuditLogService $auditLogService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, Request $request): Facility
    {
        return DB::transaction(function () use ($data, $request): Facility {
            $payload = $this->normalizePayload($data);
            $facility = Facility::query()->create($payload);

            $this->auditLogService->log(
                event: 'platform.facility.created',
                action: 'create',
                request: $request,
                subject: $facility,
                newValues: $facility->toArray(),
            );

            return $facility;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Facility $facility, array $data, Request $request): Facility
    {
        return DB::transaction(function () use ($facility, $data, $request): Facility {
            $oldValues = $facility->toArray();
            $payload = $this->normalizePayload($data, $facility);
            $facility->update($payload);

            $this->auditLogService->log(
                event: 'platform.facility.updated',
                action: 'update',
                request: $request,
                subject: $facility,
                oldValues: $oldValues,
                newValues: $facility->fresh()->toArray(),
            );

            return $facility->fresh();
        });
    }

    public function toggleStatus(Facility $facility, Request $request): Facility
    {
        return DB::transaction(function () use ($facility, $request): Facility {
            $oldValues = $facility->only(['status', 'is_active']);
            $nextStatus = $facility->is_active ? 'inactive' : 'active';
            $facility->update([
                'status' => $nextStatus,
                'is_active' => $nextStatus === 'active',
            ]);

            $this->auditLogService->log(
                event: 'platform.facility.status_toggled',
                action: 'update',
                request: $request,
                subject: $facility,
                oldValues: $oldValues,
                newValues: $facility->fresh()->only(['status', 'is_active']),
            );

            return $facility->fresh();
        });
    }

    public function canDelete(Facility $facility): bool
    {
        $facility->loadCount(['gyms', 'branches']);

        return $facility->gyms_count === 0 && $facility->branches_count === 0;
    }

    public function delete(Facility $facility, Request $request): void
    {
        DB::transaction(function () use ($facility, $request): void {
            $oldValues = $facility->toArray();
            $facility->delete();

            $this->auditLogService->log(
                event: 'platform.facility.deleted',
                action: 'delete',
                request: $request,
                subject: $facility,
                oldValues: $oldValues,
            );
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePayload(array $data, ?Facility $facility = null): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $slug = $this->makeUniqueSlug($name, $facility);
        $status = (string) ($data['status'] ?? 'active');

        return [
            'name' => $name,
            'icon' => filled($data['icon'] ?? null) ? trim((string) $data['icon']) : null,
            'description' => filled($data['description'] ?? null) ? trim((string) $data['description']) : null,
            'slug' => $slug,
            'status' => $status,
            'is_active' => $status === 'active',
        ];
    }

    private function makeUniqueSlug(string $name, ?Facility $facility = null): string
    {
        $baseSlug = Str::slug($name) ?: 'facility';
        $slug = $baseSlug;
        $suffix = 2;

        while (
            Facility::query()
                ->when($facility, fn ($query) => $query->whereKeyNot($facility->id))
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $baseSlug.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
