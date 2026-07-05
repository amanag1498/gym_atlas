<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('member_onboarding_completed')->default(false)->after('is_active');
            $table->unsignedTinyInteger('member_onboarding_step')->default(1)->after('member_onboarding_completed');
            $table->boolean('trainer_onboarding_completed')->default(false)->after('member_onboarding_step');
            $table->unsignedTinyInteger('trainer_onboarding_step')->default(1)->after('trainer_onboarding_completed');

            $table->index(['member_onboarding_completed', 'member_onboarding_step'], 'users_member_onboarding_idx');
            $table->index(['trainer_onboarding_completed', 'trainer_onboarding_step'], 'users_trainer_onboarding_idx');
        });

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->text('availability_notes')->nullable()->after('languages');
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->boolean('gym_onboarding_completed')->default(false)->after('trial_available');
            $table->index('gym_onboarding_completed');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropIndex(['gym_onboarding_completed']);
            $table->dropColumn('gym_onboarding_completed');
        });

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->dropColumn('availability_notes');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_member_onboarding_idx');
            $table->dropIndex('users_trainer_onboarding_idx');
            $table->dropColumn([
                'member_onboarding_completed',
                'member_onboarding_step',
                'trainer_onboarding_completed',
                'trainer_onboarding_step',
            ]);
        });
    }
};
