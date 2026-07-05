<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workout_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('audience')->nullable();
            $table->string('goal')->nullable();
            $table->string('difficulty')->nullable();
            $table->string('program_type')->nullable();
            $table->string('equipment_profile')->nullable();
            $table->unsignedSmallInteger('days_per_week')->nullable();
            $table->unsignedSmallInteger('duration_weeks')->nullable();
            $table->unsignedSmallInteger('estimated_session_minutes')->nullable();
            $table->text('description')->nullable();
            $table->text('coach_notes')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->string('status')->default('active');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('workout_templates', function (Blueprint $table): void {
            $table->foreignId('workout_book_id')->nullable()->after('branch_id')->constrained()->nullOnDelete();
            $table->boolean('is_public_catalog')->default(false)->after('status');
            $table->string('program_type')->nullable()->after('difficulty');
            $table->string('equipment_profile')->nullable()->after('program_type');
            $table->unsignedSmallInteger('estimated_session_minutes')->nullable()->after('duration_weeks');
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropForeign(['gym_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['trainer_id']);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable()->change();
            $table->foreignId('branch_id')->nullable()->change();
            $table->foreignId('trainer_id')->nullable()->change();
            $table->foreignId('created_by_user_id')->nullable()->after('trainer_id')->constrained('users')->nullOnDelete();
            $table->foreignId('source_workout_book_id')->nullable()->after('created_by_user_id')->constrained('workout_books')->nullOnDelete();
            $table->string('plan_origin')->default('trainer_assigned')->after('source_workout_book_id');
            $table->boolean('is_member_editable')->default(false)->after('plan_origin');
            $table->unsignedSmallInteger('estimated_session_minutes')->nullable()->after('duration_weeks');
            $table->string('equipment_profile')->nullable()->after('estimated_session_minutes');
        });

        Schema::table('workout_sessions', function (Blueprint $table): void {
            //
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreign('gym_id')->references('id')->on('gyms')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->nullOnDelete();
            $table->foreign('trainer_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropForeign(['gym_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['trainer_id']);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('created_by_user_id');
            $table->dropConstrainedForeignId('source_workout_book_id');
            $table->dropColumn([
                'plan_origin',
                'is_member_editable',
                'estimated_session_minutes',
                'equipment_profile',
            ]);
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreignId('gym_id')->nullable(false)->change();
            $table->foreignId('branch_id')->nullable(false)->change();
            $table->foreignId('trainer_id')->nullable(false)->change();
        });

        Schema::table('workout_plans', function (Blueprint $table): void {
            $table->foreign('gym_id')->references('id')->on('gyms')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches')->cascadeOnDelete();
            $table->foreign('trainer_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('workout_templates', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('workout_book_id');
            $table->dropColumn([
                'is_public_catalog',
                'program_type',
                'equipment_profile',
                'estimated_session_minutes',
            ]);
        });

        Schema::dropIfExists('workout_books');
    }
};
