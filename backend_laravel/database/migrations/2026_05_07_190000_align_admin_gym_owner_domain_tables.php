<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            if (! Schema::hasColumn('gyms', 'logo')) {
                $table->string('logo')->nullable()->after('logo_url');
            }
            if (! Schema::hasColumn('gyms', 'cover_image')) {
                $table->string('cover_image')->nullable()->after('cover_image_url');
            }
            if (! Schema::hasColumn('gyms', 'address')) {
                $table->string('address')->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('gyms', 'opening_time')) {
                $table->time('opening_time')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('gyms', 'closing_time')) {
                $table->time('closing_time')->nullable()->after('opening_time');
            }
            if (! Schema::hasColumn('gyms', 'show_pricing')) {
                $table->boolean('show_pricing')->default(true)->after('public_listing_enabled');
            }
            if (! Schema::hasColumn('gyms', 'contact_visible')) {
                $table->boolean('contact_visible')->default(false)->after('trial_available');
            }
        });

        Schema::table('branches', function (Blueprint $table): void {
            if (! Schema::hasColumn('branches', 'address')) {
                $table->string('address')->nullable()->after('address_line');
            }
            if (! Schema::hasColumn('branches', 'opening_time')) {
                $table->time('opening_time')->nullable()->after('longitude');
            }
            if (! Schema::hasColumn('branches', 'closing_time')) {
                $table->time('closing_time')->nullable()->after('opening_time');
            }
        });

        Schema::table('facilities', function (Blueprint $table): void {
            if (! Schema::hasColumn('facilities', 'icon')) {
                $table->string('icon')->nullable()->after('name');
            }
            if (! Schema::hasColumn('facilities', 'status')) {
                $table->string('status')->default('active')->after('description');
            }
        });

        Schema::table('facility_gym', function (Blueprint $table): void {
            if (! Schema::hasColumn('facility_gym', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('facility_id')->constrained('branches')->nullOnDelete();
            }
        });

        if (! Schema::hasTable('gym_photos')) {
            Schema::create('gym_photos', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
                $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
                $table->string('image_path');
                $table->string('type');
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->index(['gym_id', 'branch_id', 'type']);
                $table->index(['created_at']);
            });
        }

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('trainer_profiles', 'specialization')) {
                $table->string('specialization')->nullable()->after('bio');
            }
            if (! Schema::hasColumn('trainer_profiles', 'status')) {
                $table->string('status')->default('active')->after('certifications');
            }
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            if (! Schema::hasColumn('member_profiles', 'assigned_trainer_id')) {
                $table->foreignId('assigned_trainer_id')->nullable()->after('assigned_trainer_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('member_profiles', 'status')) {
                $table->string('status')->default('active')->after('emergency_contact_phone');
            }
        });

        Schema::table('gym_user', function (Blueprint $table): void {
            if (! Schema::hasColumn('gym_user', 'branch_id')) {
                $table->foreignId('branch_id')->nullable()->after('user_id')->constrained('branches')->nullOnDelete();
            }
            if (! Schema::hasColumn('gym_user', 'role_name')) {
                $table->string('role_name')->nullable()->after('branch_id');
            }
            if (! Schema::hasColumn('gym_user', 'permissions')) {
                $table->json('permissions')->nullable()->after('custom_permissions');
            }
            if (! Schema::hasColumn('gym_user', 'status')) {
                $table->string('status')->default('active')->after('permissions');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            if (! Schema::hasColumn('payments', 'payment_status')) {
                $table->string('payment_status')->default('paid')->after('payment_mode');
            }
            if (! Schema::hasColumn('payments', 'payment_date')) {
                $table->timestamp('payment_date')->nullable()->after('paid_at');
            }
            if (! Schema::hasColumn('payments', 'collected_by')) {
                $table->foreignId('collected_by')->nullable()->after('received_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('payments', 'receipt_number')) {
                $table->string('receipt_number')->nullable()->after('external_reference');
            }
        });

        Schema::table('custom_fee_audit_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('custom_fee_audit_logs', 'gym_id')) {
                $table->foreignId('gym_id')->nullable()->after('id')->constrained('gyms')->nullOnDelete();
            }
            if (! Schema::hasColumn('custom_fee_audit_logs', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('changed_at');
            }
            if (! Schema::hasColumn('custom_fee_audit_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        Schema::table('announcements', function (Blueprint $table): void {
            if (! Schema::hasColumn('announcements', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('created_by_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('announcements', 'status')) {
                $table->string('status')->default('sent')->after('message');
            }
        });

        Schema::table('notifications', function (Blueprint $table): void {
            if (! Schema::hasColumn('notifications', 'message')) {
                $table->text('message')->nullable()->after('title');
            }
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_logs', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('actor_user_id')->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn('activity_logs', 'created_at')) {
                $table->timestamp('created_at')->nullable()->after('occurred_at');
            }
            if (! Schema::hasColumn('activity_logs', 'updated_at')) {
                $table->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        $this->backfillGyms();
        $this->backfillBranches();
        $this->backfillFacilities();
        $this->backfillFacilityGymBranchScope();
        $this->backfillGymPhotos();
        $this->backfillTrainerProfiles();
        $this->backfillMemberProfiles();
        $this->backfillGymUserAssignments();
        $this->backfillPayments();
        $this->backfillCustomFeeAuditLogs();
        $this->backfillAnnouncements();
        $this->backfillNotifications();
        $this->backfillActivityLogs();
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            foreach (['user_id', 'created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('activity_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('notifications', function (Blueprint $table): void {
            if (Schema::hasColumn('notifications', 'message')) {
                $table->dropColumn('message');
            }
        });

        Schema::table('announcements', function (Blueprint $table): void {
            foreach (['created_by', 'status'] as $column) {
                if (Schema::hasColumn('announcements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('custom_fee_audit_logs', function (Blueprint $table): void {
            foreach (['gym_id', 'created_at', 'updated_at'] as $column) {
                if (Schema::hasColumn('custom_fee_audit_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            foreach (['payment_status', 'payment_date', 'collected_by', 'receipt_number'] as $column) {
                if (Schema::hasColumn('payments', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('gym_user', function (Blueprint $table): void {
            foreach (['branch_id', 'role_name', 'permissions', 'status'] as $column) {
                if (Schema::hasColumn('gym_user', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('member_profiles', function (Blueprint $table): void {
            foreach (['assigned_trainer_id', 'status'] as $column) {
                if (Schema::hasColumn('member_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            foreach (['specialization', 'status'] as $column) {
                if (Schema::hasColumn('trainer_profiles', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasTable('gym_photos')) {
            Schema::dropIfExists('gym_photos');
        }

        Schema::table('facility_gym', function (Blueprint $table): void {
            if (Schema::hasColumn('facility_gym', 'branch_id')) {
                $table->dropColumn('branch_id');
            }
        });

        Schema::table('facilities', function (Blueprint $table): void {
            foreach (['icon', 'status'] as $column) {
                if (Schema::hasColumn('facilities', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('branches', function (Blueprint $table): void {
            foreach (['address', 'opening_time', 'closing_time'] as $column) {
                if (Schema::hasColumn('branches', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('gyms', function (Blueprint $table): void {
            foreach (['logo', 'cover_image', 'address', 'opening_time', 'closing_time', 'show_pricing', 'contact_visible'] as $column) {
                if (Schema::hasColumn('gyms', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function backfillGyms(): void
    {
        DB::table('gyms')->orderBy('id')->get()->each(function (object $gym): void {
            DB::table('gyms')->where('id', $gym->id)->update([
                'logo' => $gym->logo ?: ($gym->logo_url ?? null),
                'cover_image' => $gym->cover_image ?: ($gym->cover_image_url ?? null),
                'address' => $gym->address ?: ($gym->address_line ?? null),
                'opening_time' => $gym->opening_time ?: $this->extractTime($gym->timings ?? null, 'open'),
                'closing_time' => $gym->closing_time ?: $this->extractTime($gym->timings ?? null, 'close'),
                'show_pricing' => is_null($gym->show_pricing) ? (bool) ($gym->pricing_visible ?? true) : $gym->show_pricing,
                'contact_visible' => (bool) ($gym->contact_visible ?? false),
            ]);
        });
    }

    private function backfillBranches(): void
    {
        DB::table('branches')->orderBy('id')->get()->each(function (object $branch): void {
            DB::table('branches')->where('id', $branch->id)->update([
                'address' => $branch->address ?: ($branch->address_line ?? null),
                'opening_time' => $branch->opening_time ?: $this->extractTime($branch->timings ?? null, 'open'),
                'closing_time' => $branch->closing_time ?: $this->extractTime($branch->timings ?? null, 'close'),
            ]);
        });
    }

    private function backfillFacilities(): void
    {
        DB::table('facilities')->orderBy('id')->get()->each(function (object $facility): void {
            DB::table('facilities')->where('id', $facility->id)->update([
                'status' => $facility->status ?: ((bool) ($facility->is_active ?? true) ? 'active' : 'inactive'),
            ]);
        });
    }

    private function backfillFacilityGymBranchScope(): void
    {
        if (! Schema::hasTable('branch_facility')) {
            return;
        }

        $rows = DB::table('branch_facility')
            ->join('branches', 'branches.id', '=', 'branch_facility.branch_id')
            ->select('branches.gym_id', 'branch_facility.branch_id', 'branch_facility.facility_id')
            ->get();

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->gym_id.'-'.$row->facility_id;
            $grouped[$key] ??= [];
            $grouped[$key][] = (int) $row->branch_id;
        }

        foreach ($grouped as $key => $branchIds) {
            if (count(array_unique($branchIds)) !== 1) {
                continue;
            }

            [$gymId, $facilityId] = array_map('intval', explode('-', $key));

            DB::table('facility_gym')
                ->where('gym_id', $gymId)
                ->where('facility_id', $facilityId)
                ->update(['branch_id' => $branchIds[0]]);
        }
    }

    private function backfillGymPhotos(): void
    {
        $photoExists = function (int $gymId, ?int $branchId, string $imagePath, string $type): bool {
            return DB::table('gym_photos')
                ->where('gym_id', $gymId)
                ->where('branch_id', $branchId)
                ->where('image_path', $imagePath)
                ->where('type', $type)
                ->exists();
        };

        DB::table('gyms')->orderBy('id')->get()->each(function (object $gym) use ($photoExists): void {
            $entries = [];

            if (! empty($gym->logo_url)) {
                $entries[] = ['image_path' => $gym->logo_url, 'type' => 'logo', 'sort_order' => 0];
            }
            if (! empty($gym->cover_image_url)) {
                $entries[] = ['image_path' => $gym->cover_image_url, 'type' => 'cover', 'sort_order' => 0];
            }

            foreach ($this->decodeJsonArray($gym->photo_urls ?? null) as $index => $imagePath) {
                $entries[] = ['image_path' => $imagePath, 'type' => 'gallery', 'sort_order' => $index + 1];
            }

            foreach ($entries as $entry) {
                if (! $photoExists((int) $gym->id, null, $entry['image_path'], $entry['type'])) {
                    DB::table('gym_photos')->insert([
                        'gym_id' => $gym->id,
                        'branch_id' => null,
                        'image_path' => $entry['image_path'],
                        'type' => $entry['type'],
                        'sort_order' => $entry['sort_order'],
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });

        DB::table('branches')->orderBy('id')->get()->each(function (object $branch) use ($photoExists): void {
            foreach ($this->decodeJsonArray($branch->photo_urls ?? null) as $index => $imagePath) {
                if (! $photoExists((int) $branch->gym_id, (int) $branch->id, $imagePath, 'gallery')) {
                    DB::table('gym_photos')->insert([
                        'gym_id' => $branch->gym_id,
                        'branch_id' => $branch->id,
                        'image_path' => $imagePath,
                        'type' => 'gallery',
                        'sort_order' => $index + 1,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        });
    }

    private function backfillTrainerProfiles(): void
    {
        DB::table('trainer_profiles')->orderBy('id')->get()->each(function (object $trainer): void {
            $specializations = $this->decodeJsonArray($trainer->specializations ?? null);

            DB::table('trainer_profiles')->where('id', $trainer->id)->update([
                'specialization' => $trainer->specialization ?: ($specializations[0] ?? null),
                'status' => $trainer->status ?: ((bool) ($trainer->is_active ?? true) ? 'active' : 'inactive'),
            ]);
        });
    }

    private function backfillMemberProfiles(): void
    {
        DB::table('member_profiles')->orderBy('id')->get()->each(function (object $member): void {
            DB::table('member_profiles')->where('id', $member->id)->update([
                'assigned_trainer_id' => $member->assigned_trainer_id ?: ($member->assigned_trainer_user_id ?? null),
                'status' => $member->status ?: ($member->membership_status ?: ((bool) ($member->is_active ?? true) ? 'active' : 'inactive')),
            ]);
        });
    }

    private function backfillGymUserAssignments(): void
    {
        DB::table('gym_user')->orderBy('id')->get()->each(function (object $pivot): void {
            $branchId = DB::table('branch_user')
                ->join('branches', 'branches.id', '=', 'branch_user.branch_id')
                ->where('branch_user.user_id', $pivot->user_id)
                ->where('branches.gym_id', $pivot->gym_id)
                ->orderByDesc('branch_user.is_primary')
                ->value('branch_user.branch_id');

            $roleName = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->where('model_has_roles.model_id', $pivot->user_id)
                ->orderByRaw("case when roles.name = 'gym_owner' then 1 when roles.name = 'branch_manager' then 2 when roles.name = 'gym_staff' then 3 else 4 end")
                ->value('roles.name');

            DB::table('gym_user')->where('id', $pivot->id)->update([
                'branch_id' => $pivot->branch_id ?: $branchId,
                'role_name' => $pivot->role_name ?: $roleName,
                'permissions' => $pivot->permissions ?: ($pivot->custom_permissions ?? null),
                'status' => $pivot->status ?: 'active',
            ]);
        });
    }

    private function backfillPayments(): void
    {
        DB::table('payments')->orderBy('id')->get()->each(function (object $payment): void {
            $receiptNumber = DB::table('payment_receipts')
                ->where('payment_id', $payment->id)
                ->value('receipt_number');

            DB::table('payments')->where('id', $payment->id)->update([
                'payment_status' => $payment->payment_status ?: match ($payment->status) {
                    'reversed' => 'refunded',
                    'failed' => 'failed',
                    default => 'paid',
                },
                'payment_date' => $payment->payment_date ?: ($payment->paid_at ?? $payment->created_at ?? now()),
                'collected_by' => $payment->collected_by ?: ($payment->received_by_user_id ?? null),
                'receipt_number' => $payment->receipt_number ?: $receiptNumber,
            ]);
        });
    }

    private function backfillCustomFeeAuditLogs(): void
    {
        DB::table('custom_fee_audit_logs')->orderBy('id')->get()->each(function (object $log): void {
            $gymId = DB::table('member_memberships')
                ->where('id', $log->member_membership_id)
                ->value('gym_id');

            DB::table('custom_fee_audit_logs')->where('id', $log->id)->update([
                'gym_id' => $log->gym_id ?: $gymId,
                'created_at' => $log->created_at ?: ($log->changed_at ?? now()),
                'updated_at' => $log->updated_at ?: ($log->changed_at ?? now()),
            ]);
        });
    }

    private function backfillAnnouncements(): void
    {
        DB::table('announcements')->orderBy('id')->get()->each(function (object $announcement): void {
            DB::table('announcements')->where('id', $announcement->id)->update([
                'created_by' => $announcement->created_by ?: ($announcement->created_by_user_id ?? null),
                'status' => $announcement->status ?: 'sent',
            ]);
        });
    }

    private function backfillNotifications(): void
    {
        DB::table('notifications')->orderBy('id')->get()->each(function (object $notification): void {
            DB::table('notifications')->where('id', $notification->id)->update([
                'message' => $notification->message ?: ($notification->body ?? null),
            ]);
        });
    }

    private function backfillActivityLogs(): void
    {
        DB::table('activity_logs')->orderBy('id')->get()->each(function (object $log): void {
            DB::table('activity_logs')->where('id', $log->id)->update([
                'user_id' => $log->user_id ?: ($log->actor_user_id ?? null),
                'created_at' => $log->created_at ?: ($log->occurred_at ?? now()),
                'updated_at' => $log->updated_at ?: ($log->occurred_at ?? now()),
            ]);
        });
    }

    /**
     * @return list<string>
     */
    private function decodeJsonArray(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter($value, 'is_string'));
        }

        if (! is_string($value) || trim($value) === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? array_values(array_filter($decoded, 'is_string')) : [];
    }

    private function extractTime(mixed $value, string $key): ?string
    {
        $timings = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($timings)) {
            return null;
        }

        foreach ($timings as $slot) {
            if (is_array($slot) && isset($slot[$key]) && is_string($slot[$key])) {
                return substr($slot[$key], 0, 5);
            }

            if (is_array($slot)) {
                foreach ($slot as $nested) {
                    if (is_array($nested) && isset($nested[$key]) && is_string($nested[$key])) {
                        return substr($nested[$key], 0, 5);
                    }
                }
            }
        }

        return null;
    }
};
