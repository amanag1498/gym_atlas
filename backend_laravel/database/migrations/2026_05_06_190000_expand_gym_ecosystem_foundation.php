<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('state');
            $table->string('country')->default('India');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['name', 'state', 'country']);
        });

        Schema::create('facilities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('platform_banners', function (Blueprint $table): void {
            $table->id();
            $table->string('title');
            $table->string('image_url')->nullable();
            $table->string('link_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->after('owner_user_id')->constrained('cities')->nullOnDelete();
            $table->text('description')->nullable()->after('name');
            $table->string('logo_url')->nullable()->after('description');
            $table->string('cover_image_url')->nullable()->after('logo_url');
            $table->json('photo_urls')->nullable()->after('cover_image_url');
            $table->string('pincode', 20)->nullable()->after('state');
            $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('timings')->nullable()->after('longitude');
            $table->json('weekly_off')->nullable()->after('timings');
            $table->boolean('is_active')->default(true)->after('status');
            $table->string('approval_status')->default('pending')->after('is_active');
            $table->text('approval_notes')->nullable()->after('approval_status');
            $table->foreignId('approved_by_user_id')->nullable()->after('approval_notes')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by_user_id');
            $table->foreignId('rejected_by_user_id')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_user_id');
            $table->boolean('is_verified')->default(false)->after('rejected_at');
            $table->foreignId('verified_by_user_id')->nullable()->after('is_verified')->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable()->after('verified_by_user_id');
            $table->boolean('public_listing_enabled')->default(false)->after('verified_at');
            $table->string('public_listing_approval_status')->default('pending')->after('public_listing_enabled');
            $table->foreignId('public_listing_approved_by_user_id')->nullable()->after('public_listing_approval_status')->constrained('users')->nullOnDelete();
            $table->timestamp('public_listing_approved_at')->nullable()->after('public_listing_approved_by_user_id');
            $table->boolean('pricing_visible')->default(false)->after('public_listing_approved_at');
            $table->boolean('trial_available')->default(false)->after('pricing_visible');
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->foreignId('city_id')->nullable()->after('gym_id')->constrained('cities')->nullOnDelete();
            $table->string('pincode', 20)->nullable()->after('state');
            $table->decimal('latitude', 10, 7)->nullable()->after('pincode');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->json('timings')->nullable()->after('longitude');
            $table->json('weekly_off')->nullable()->after('timings');
            $table->json('photo_urls')->nullable()->after('weekly_off');
            $table->boolean('is_active')->default(true)->after('status');
        });

        Schema::table('gym_user', function (Blueprint $table): void {
            $table->json('custom_permissions')->nullable()->after('user_id');
            $table->boolean('is_primary')->default(false)->after('custom_permissions');
        });

        Schema::table('branch_user', function (Blueprint $table): void {
            $table->json('custom_permissions')->nullable()->after('user_id');
            $table->boolean('is_primary')->default(false)->after('custom_permissions');
        });

        Schema::create('facility_gym', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'gym_id']);
        });

        Schema::create('branch_facility', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('facility_id')->constrained('facilities')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['facility_id', 'branch_id']);
        });

        Schema::create('trainer_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->json('specializations')->nullable();
            $table->unsignedSmallInteger('experience_years')->default(0);
            $table->json('certifications')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('member_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('assigned_trainer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('fitness_goal')->nullable();
            $table->decimal('height_cm', 5, 2)->nullable();
            $table->decimal('weight_kg', 5, 2)->nullable();
            $table->string('experience_level')->nullable();
            $table->text('medical_notes')->nullable();
            $table->text('injury_notes')->nullable();
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();
            $table->string('membership_status')->default('active');
            $table->date('membership_expires_on')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->after('actor_user_id')->constrained('gyms')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->after('gym_id')->constrained('branches')->nullOnDelete();
            $table->string('action')->nullable()->after('event');
            $table->string('actor_role')->nullable()->after('action');
            $table->json('old_values')->nullable()->after('context');
            $table->json('new_values')->nullable()->after('old_values');
        });

        DB::table('gyms')->update([
            'approval_status' => 'approved',
            'approved_at' => now(),
            'public_listing_approval_status' => 'approved',
            'public_listing_approved_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('gym_id');
            $table->dropConstrainedForeignId('branch_id');
            $table->dropColumn(['action', 'actor_role', 'old_values', 'new_values']);
        });

        Schema::dropIfExists('member_profiles');
        Schema::dropIfExists('trainer_profiles');
        Schema::dropIfExists('branch_facility');
        Schema::dropIfExists('facility_gym');

        Schema::table('branch_user', function (Blueprint $table): void {
            $table->dropColumn(['custom_permissions', 'is_primary']);
        });

        Schema::table('gym_user', function (Blueprint $table): void {
            $table->dropColumn(['custom_permissions', 'is_primary']);
        });

        Schema::table('branches', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
            $table->dropColumn(['pincode', 'latitude', 'longitude', 'timings', 'weekly_off', 'photo_urls', 'is_active']);
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('city_id');
            $table->dropConstrainedForeignId('approved_by_user_id');
            $table->dropConstrainedForeignId('rejected_by_user_id');
            $table->dropConstrainedForeignId('verified_by_user_id');
            $table->dropConstrainedForeignId('public_listing_approved_by_user_id');
            $table->dropColumn([
                'description',
                'logo_url',
                'cover_image_url',
                'photo_urls',
                'pincode',
                'latitude',
                'longitude',
                'timings',
                'weekly_off',
                'is_active',
                'approval_status',
                'approval_notes',
                'approved_at',
                'rejected_at',
                'is_verified',
                'verified_at',
                'public_listing_enabled',
                'public_listing_approval_status',
                'public_listing_approved_at',
                'pricing_visible',
                'trial_available',
            ]);
        });

        Schema::dropIfExists('platform_banners');
        Schema::dropIfExists('facilities');
        Schema::dropIfExists('cities');
    }
};
