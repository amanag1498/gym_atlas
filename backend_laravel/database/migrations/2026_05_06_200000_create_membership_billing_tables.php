<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('membership_plans', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('duration_days');
            $table->decimal('plan_price', 10, 2);
            $table->decimal('joining_fee', 10, 2)->default(0);
            $table->boolean('pt_included')->default(false);
            $table->text('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'branch_id', 'status']);
        });

        Schema::create('member_memberships', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('membership_plan_id')->constrained('membership_plans')->cascadeOnDelete();
            $table->date('start_date');
            $table->date('expiry_date');
            $table->string('status')->default('active');
            $table->decimal('default_plan_price', 10, 2);
            $table->decimal('default_joining_fee', 10, 2)->default(0);
            $table->boolean('custom_fee_enabled')->default(false);
            $table->decimal('custom_fee_amount', 10, 2)->default(0);
            $table->string('discount_type')->default('none');
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('custom_joining_fee', 10, 2)->default(0);
            $table->boolean('joining_fee_waived')->default(false);
            $table->decimal('partial_month_fee', 10, 2)->default(0);
            $table->decimal('pt_custom_fee', 10, 2)->default(0);
            $table->decimal('final_payable_amount', 10, 2)->default(0);
            $table->decimal('amount_paid', 10, 2)->default(0);
            $table->decimal('due_amount', 10, 2)->default(0);
            $table->date('due_date')->nullable();
            $table->string('payment_status')->default('unpaid');
            $table->text('custom_fee_reason')->nullable();
            $table->foreignId('approved_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['gym_id', 'branch_id', 'member_id', 'payment_status']);
        });

        Schema::create('payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained('gyms')->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->foreignId('member_membership_id')->constrained('member_memberships')->cascadeOnDelete();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('received_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('payment_mode');
            $table->string('status')->default('recorded');
            $table->string('external_reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->index(['member_membership_id', 'status']);
        });

        Schema::create('payment_receipts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payment_id')->constrained('payments')->cascadeOnDelete();
            $table->string('receipt_number')->unique();
            $table->string('status')->default('pending_generation');
            $table->timestamp('generated_at')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();
        });

        Schema::create('custom_fee_audit_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('member_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('member_membership_id')->constrained('member_memberships')->cascadeOnDelete();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->foreignId('changed_by')->constrained('users')->cascadeOnDelete();
            $table->text('reason');
            $table->timestamp('changed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_fee_audit_logs');
        Schema::dropIfExists('payment_receipts');
        Schema::dropIfExists('payments');
        Schema::dropIfExists('member_memberships');
        Schema::dropIfExists('membership_plans');
    }
};
