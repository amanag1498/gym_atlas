<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_member_notes', function (Blueprint $table): void {
            $table->foreignId('independent_trainer_member_relationship_id')
                ->nullable()
                ->after('member_id')
                ->constrained('independent_trainer_member_relationships')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trainer_member_notes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('independent_trainer_member_relationship_id');
        });
    }
};
