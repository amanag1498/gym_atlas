<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_subscription_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('billing_period')->default('month');
            $table->unsignedInteger('billing_interval_count')->default(1);
            $table->decimal('price', 10, 2)->default(0);
            $table->decimal('setup_fee', 10, 2)->default(0);
            $table->unsignedInteger('trial_days')->default(0);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('included_services')->nullable();
            $table->json('feature_highlights')->nullable();
            $table->timestamps();

            $table->index(['status', 'is_default'], 'platform_sub_plans_status_default_idx');
            $table->index(['billing_period', 'billing_interval_count'], 'platform_sub_plans_period_interval_idx');
        });

        Schema::create('gym_platform_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('platform_subscription_plan_id')->nullable()->constrained('platform_subscription_plans')->nullOnDelete();
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('active');
            $table->date('starts_at')->nullable();
            $table->date('renews_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('trial_ends_at')->nullable();
            $table->decimal('billing_amount', 10, 2)->default(0);
            $table->decimal('setup_fee_amount', 10, 2)->default(0);
            $table->boolean('auto_renew')->default(true);
            $table->json('included_services')->nullable();
            $table->json('plan_snapshot')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'status'], 'gym_platform_subs_gym_status_idx');
            $table->index(['platform_subscription_plan_id', 'status'], 'gym_platform_subs_plan_status_idx');
            $table->index(['renews_at', 'status'], 'gym_platform_subs_renews_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_platform_subscriptions');
        Schema::dropIfExists('platform_subscription_plans');
    }
};
