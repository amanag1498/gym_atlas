<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compensation_profiles', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('worker_type');
            $table->decimal('monthly_salary', 12, 2)->default(0);
            $table->unsignedTinyInteger('payout_day')->default(1);
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['gym_id', 'user_id'], 'comp_profile_gym_user_uq');
            $table->index(['gym_id', 'branch_id', 'worker_type'], 'comp_profile_scope_idx');
        });

        Schema::create('membership_commission_allocations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('member_membership_id');
            $table->foreignId('recipient_user_id');
            $table->foreignId('copied_from_allocation_id')->nullable();
            $table->string('recipient_type');
            $table->string('category');
            $table->string('calculation_type');
            $table->decimal('value', 12, 2);
            $table->string('recurrence');
            $table->decimal('commissionable_extra_amount', 12, 2);
            $table->decimal('expected_commission_amount', 12, 2);
            $table->string('status')->default('active');
            $table->timestamps();

            $table->index(['member_membership_id', 'status'], 'member_comm_membership_status_idx');
            $table->index(['gym_id', 'recipient_user_id', 'status'], 'commission_alloc_recipient_idx');
            $table->foreign('member_membership_id', 'member_comm_membership_fk')->references('id')->on('member_memberships')->cascadeOnDelete();
            $table->foreign('recipient_user_id', 'member_comm_recipient_fk')->references('id')->on('users')->restrictOnDelete();
            $table->foreign('copied_from_allocation_id', 'member_comm_source_fk')->references('id')->on('membership_commission_allocations')->nullOnDelete();
        });

        Schema::create('commission_earnings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('membership_commission_allocation_id');
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_user_id');
            $table->decimal('commissionable_collected_amount', 12, 2);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('earned');
            $table->timestamp('earned_at');
            $table->timestamp('reversed_at')->nullable();
            $table->timestamps();

            $table->unique(['membership_commission_allocation_id', 'payment_id'], 'commission_earning_source_unique');
            $table->index(['gym_id', 'recipient_user_id', 'earned_at'], 'commission_earning_period_idx');
            $table->foreign('membership_commission_allocation_id', 'comm_earning_allocation_fk')->references('id')->on('membership_commission_allocations')->cascadeOnDelete();
            $table->foreign('recipient_user_id', 'comm_earning_recipient_fk')->references('id')->on('users')->restrictOnDelete();
        });

        Schema::create('payroll_statements', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->date('period_start');
            $table->date('period_end');
            $table->decimal('salary_amount', 12, 2)->default(0);
            $table->decimal('commission_amount', 12, 2)->default(0);
            $table->decimal('adjustment_amount', 12, 2)->default(0);
            $table->decimal('deduction_amount', 12, 2)->default(0);
            $table->decimal('net_payable_amount', 12, 2)->default(0);
            $table->decimal('paid_amount', 12, 2)->default(0);
            $table->string('status')->default('draft');
            $table->text('notes')->nullable();
            $table->timestamp('generated_at')->nullable();
            $table->foreignId('approved_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->unique(['gym_id', 'user_id', 'period_start'], 'payroll_stmt_period_uq');
            $table->index(['gym_id', 'period_start', 'status'], 'payroll_stmt_scope_idx');
        });

        Schema::create('payroll_payments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_statement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('gym_ledger_entry_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('payment_mode');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_payments');
        Schema::dropIfExists('payroll_statements');
        Schema::dropIfExists('commission_earnings');
        Schema::dropIfExists('membership_commission_allocations');
        Schema::dropIfExists('compensation_profiles');
    }
};
