<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true)->after('email_verified_at');
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->boolean('is_featured')->default(false)->after('trial_available');
            $table->boolean('is_promoted')->default(false)->after('is_featured');
            $table->unsignedInteger('featured_sort_order')->default(0)->after('is_promoted');
        });

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });

        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropColumn(['is_featured', 'is_promoted', 'featured_sort_order']);
        });
    }
};
