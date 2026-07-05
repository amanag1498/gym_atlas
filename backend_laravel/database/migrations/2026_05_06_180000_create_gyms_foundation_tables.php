<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gyms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('branches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('timezone')->default('Asia/Kolkata');
            $table->string('address_line')->nullable();
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();
            $table->string('status')->default('active');
            $table->timestamps();
        });

        Schema::create('gym_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['gym_id', 'user_id']);
        });

        Schema::create('branch_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['branch_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_user');
        Schema::dropIfExists('gym_user');
        Schema::dropIfExists('branches');
        Schema::dropIfExists('gyms');
    }
};
