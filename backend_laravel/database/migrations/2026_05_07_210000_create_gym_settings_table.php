<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('gym_settings')) {
            return;
        }

        Schema::create('gym_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->string('key');
            $table->json('value')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_settings');
    }
};
