<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('trainer_profiles')
            ->whereNotNull('gym_id')
            ->where(function ($query): void {
                $query->where('is_active', false)
                    ->orWhere('status', 'inactive');
            })
            ->orderBy('id')
            ->get()
            ->each(function (object $profile): void {
                DB::transaction(function () use ($profile): void {
                    $gymBranchIds = DB::table('branches')
                        ->where('gym_id', $profile->gym_id)
                        ->pluck('id');

                    if ($gymBranchIds->isNotEmpty()) {
                        DB::table('branch_user')
                            ->where('user_id', $profile->user_id)
                            ->whereIn('branch_id', $gymBranchIds)
                            ->delete();
                    }

                    DB::table('gym_user')
                        ->where('user_id', $profile->user_id)
                        ->where('gym_id', $profile->gym_id)
                        ->delete();

                    DB::table('trainer_profiles')
                        ->where('id', $profile->id)
                        ->update([
                            'gym_id' => null,
                            'branch_id' => null,
                            'status' => 'active',
                            'is_active' => true,
                            'updated_at' => now(),
                        ]);

                    $latestStatusEvent = DB::table('activity_logs')
                        ->where('subject_id', $profile->user_id)
                        ->whereIn('event', [
                            'gym.trainer.status.updated',
                            'web.gym.trainer.status.updated',
                            'platform.user.deactivated',
                            'web.platform.user.deactivated',
                        ])
                        ->latest('occurred_at')
                        ->latest('id')
                        ->first(['event', 'new_values']);

                    if ($latestStatusEvent && in_array($latestStatusEvent->event, [
                        'gym.trainer.status.updated',
                        'web.gym.trainer.status.updated',
                    ], true)) {
                        $newValues = is_string($latestStatusEvent->new_values)
                            ? json_decode($latestStatusEvent->new_values, true)
                            : (array) $latestStatusEvent->new_values;

                        if (($newValues['is_active'] ?? null) === false) {
                            DB::table('users')
                                ->where('id', $profile->user_id)
                                ->update(['is_active' => true, 'updated_at' => now()]);
                        }
                    }
                });
            });
    }

    public function down(): void
    {
        // The former gym and branch relationships are intentionally not rebuilt.
    }
};
