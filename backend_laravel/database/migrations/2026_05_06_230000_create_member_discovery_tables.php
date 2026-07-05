<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gyms', function (Blueprint $table): void {
            $table->boolean('women_friendly')->default(false)->after('prevent_duplicate_same_day_checkins');
            $table->boolean('women_only')->default(false)->after('women_friendly');
        });

        Schema::create('trial_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('member_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->date('preferred_date');
            $table->time('preferred_time')->nullable();
            $table->string('status')->default('pending');
            $table->foreignId('assigned_trainer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'branch_id', 'status']);
            $table->index(['assigned_trainer_id', 'status']);
            $table->index(['member_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trial_requests');

        Schema::table('gyms', function (Blueprint $table): void {
            $table->dropColumn(['women_friendly', 'women_only']);
        });
    }
};
