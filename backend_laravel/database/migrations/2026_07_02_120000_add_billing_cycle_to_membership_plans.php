<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->string('billing_type')->default('paid')->after('name');
            $table->string('billing_period')->default('custom')->after('billing_type');
            $table->unsignedSmallInteger('billing_interval_count')->default(1)->after('billing_period');
        });

        DB::table('membership_plans')
            ->select(['id', 'duration_days', 'plan_price'])
            ->orderBy('id')
            ->chunkById(100, function ($plans): void {
                foreach ($plans as $plan) {
                    [$billingPeriod, $intervalCount] = match ((int) $plan->duration_days) {
                        7 => ['week', 1],
                        14 => ['week', 2],
                        30, 31 => ['month', 1],
                        60, 61, 62 => ['month', 2],
                        90, 91, 92 => ['quarter', 1],
                        180, 181, 182, 183 => ['month', 6],
                        365, 366 => ['year', 1],
                        default => ['custom', 1],
                    };

                    DB::table('membership_plans')
                        ->where('id', $plan->id)
                        ->update([
                            'billing_type' => (float) $plan->plan_price <= 0 ? 'free' : 'paid',
                            'billing_period' => $billingPeriod,
                            'billing_interval_count' => $intervalCount,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('membership_plans', function (Blueprint $table): void {
            $table->dropColumn([
                'billing_type',
                'billing_period',
                'billing_interval_count',
            ]);
        });
    }
};
