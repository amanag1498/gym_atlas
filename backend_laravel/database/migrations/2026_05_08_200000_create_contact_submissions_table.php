<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('contact_submissions')) {
            return;
        }

        Schema::create('contact_submissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->string('phone', 30)->nullable();
            $table->string('inquiry_type', 40);
            $table->text('message');
            $table->string('status', 40)->default('new')->index();
            $table->timestamps();

            $table->index('email');
            $table->index('inquiry_type');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
    }
};
