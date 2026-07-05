<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            if (! Schema::hasColumn('gyms', 'rejected_reason')) {
                $table->text('rejected_reason')->nullable()->after('approval_notes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            if (Schema::hasColumn('gyms', 'rejected_reason')) {
                $table->dropColumn('rejected_reason');
            }
        });
    }
};
