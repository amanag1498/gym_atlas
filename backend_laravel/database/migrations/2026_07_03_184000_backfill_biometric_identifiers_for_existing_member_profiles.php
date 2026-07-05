<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $profiles = DB::table('member_profiles')
            ->whereNotNull('gym_id')
            ->orderBy('id')
            ->get(['id', 'user_id', 'gym_id', 'membership_status', 'biometric_identifier', 'biometric_enabled']);

        foreach ($profiles as $profile) {
            $identifier = $profile->biometric_identifier ?: sprintf('BIO-G%s-M%s', $profile->gym_id, $profile->user_id);

            DB::table('member_profiles')
                ->where('id', $profile->id)
                ->update([
                    'biometric_identifier' => $identifier,
                    'biometric_enabled' => $profile->membership_status === 'active' ? 1 : (int) $profile->biometric_enabled,
                ]);
        }
    }

    public function down(): void
    {
        DB::table('member_profiles')
            ->whereNotNull('gym_id')
            ->where('biometric_identifier', 'like', 'BIO-G%-M%')
            ->update([
                'biometric_identifier' => null,
                'biometric_enabled' => 0,
            ]);
    }
};
