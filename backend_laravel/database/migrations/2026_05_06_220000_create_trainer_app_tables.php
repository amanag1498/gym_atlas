<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->string('profile_photo_url')->nullable()->after('branch_id');
            $table->text('bio')->nullable()->after('profile_photo_url');
            $table->json('languages')->nullable()->after('certifications');
            $table->string('verification_status')->default('pending')->after('is_active');
        });

        Schema::create('trainer_member_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('trainer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->string('visibility')->default('private_to_trainer');
            $table->date('follow_up_date')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['trainer_id', 'member_id']);
            $table->index(['trainer_id', 'follow_up_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trainer_member_notes');

        Schema::table('trainer_profiles', function (Blueprint $table): void {
            $table->dropColumn(['profile_photo_url', 'bio', 'languages', 'verification_status']);
        });
    }
};
