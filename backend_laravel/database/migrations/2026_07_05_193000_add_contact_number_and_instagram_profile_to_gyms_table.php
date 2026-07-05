<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->string('contact_number', 40)->nullable()->after('address');
            $table->string('instagram_profile')->nullable()->after('contact_number');
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropColumn(['contact_number', 'instagram_profile']);
        });
    }
};
