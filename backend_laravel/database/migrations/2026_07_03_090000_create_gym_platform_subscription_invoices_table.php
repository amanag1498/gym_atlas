<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_platform_subscription_invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_platform_subscription_id');
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_subscription_plan_id')->nullable();
            $table->foreignId('generated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('paid_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('invoice_number')->unique();
            $table->string('status')->default('due');
            $table->string('currency', 3)->default('INR');
            $table->date('period_starts_at');
            $table->date('period_ends_at');
            $table->timestamp('issued_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->decimal('subtotal_amount', 10, 2)->default(0);
            $table->decimal('setup_fee_amount', 10, 2)->default(0);
            $table->decimal('discount_amount', 10, 2)->default(0);
            $table->decimal('tax_amount', 10, 2)->default(0);
            $table->decimal('total_amount', 10, 2)->default(0);
            $table->string('payment_reference')->nullable();
            $table->text('payment_notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['gym_platform_subscription_id', 'period_starts_at', 'period_ends_at'], 'gym_platform_subscription_invoice_window_unique');
            $table->index(['status', 'due_at']);
            $table->index(['gym_id', 'status', 'due_at']);
            $table->index(['paid_at', 'status']);
            $table->foreign('gym_platform_subscription_id', 'gps_invoices_subscription_fk')
                ->references('id')
                ->on('gym_platform_subscriptions')
                ->cascadeOnDelete();
            $table->foreign('platform_subscription_plan_id', 'gps_invoices_plan_fk')
                ->references('id')
                ->on('platform_subscription_plans')
                ->nullOnDelete();
        });

        $subscriptions = DB::table('gym_platform_subscriptions')->orderBy('id')->get();
        $now = now();
        $today = $now->startOfDay();

        foreach ($subscriptions as $subscription) {
            $periodStartsAt = $subscription->starts_at
                ?: optional($subscription->created_at)->format('Y-m-d')
                ?: $now->toDateString();
            $periodEndsAt = $subscription->renews_at
                ?: CarbonImmutable::parse($periodStartsAt)->addDays(30)->toDateString();
            $subtotalAmount = round((float) $subscription->billing_amount, 2);
            $setupFeeAmount = round((float) $subscription->setup_fee_amount, 2);
            $totalAmount = round($subtotalAmount + $setupFeeAmount, 2);
            $dueAt = CarbonImmutable::parse($periodEndsAt);
            $status = $totalAmount <= 0
                ? 'paid'
                : ($dueAt->lt($today) ? 'overdue' : 'due');

            DB::table('gym_platform_subscription_invoices')->insert([
                'gym_platform_subscription_id' => $subscription->id,
                'gym_id' => $subscription->gym_id,
                'platform_subscription_plan_id' => $subscription->platform_subscription_plan_id,
                'generated_by_user_id' => $subscription->assigned_by_user_id,
                'paid_by_user_id' => $status === 'paid' ? $subscription->assigned_by_user_id : null,
                'invoice_number' => sprintf('PLT-%s-%04d', str_pad((string) $subscription->id, 5, '0', STR_PAD_LEFT), 1),
                'status' => $status,
                'currency' => 'INR',
                'period_starts_at' => $periodStartsAt,
                'period_ends_at' => $periodEndsAt,
                'issued_at' => $subscription->created_at ?? $now,
                'due_at' => $periodEndsAt,
                'paid_at' => $status === 'paid' ? ($subscription->created_at ?? $now) : null,
                'subtotal_amount' => $subtotalAmount,
                'setup_fee_amount' => $setupFeeAmount,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $totalAmount,
                'metadata' => json_encode([
                    'backfilled' => true,
                    'subscription_status' => $subscription->status,
                ], JSON_THROW_ON_ERROR),
                'created_at' => $subscription->created_at ?? $now,
                'updated_at' => $subscription->updated_at ?? $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_platform_subscription_invoices');
    }
};
