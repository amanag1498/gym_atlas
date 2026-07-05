<?php

use App\Enums\PaymentRecordStatus;
use App\Models\Payment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gym_ledger_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('gym_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source_type')->nullable();
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('entry_type');
            $table->string('direction');
            $table->string('category')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('reference')->nullable();
            $table->string('payment_mode')->nullable();
            $table->decimal('amount', 10, 2);
            $table->string('status')->default('posted');
            $table->timestamp('occurred_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['gym_id', 'branch_id', 'occurred_at'], 'gym_ledger_scope_occurred_idx');
            $table->index(['gym_id', 'status', 'direction'], 'gym_ledger_status_direction_idx');
            $table->unique(['source_type', 'source_id'], 'gym_ledger_source_unique_idx');
        });

        DB::table('payments')
            ->orderBy('id')
            ->get()
            ->each(function (object $payment): void {
                DB::table('gym_ledger_entries')->updateOrInsert(
                    [
                        'source_type' => Payment::class,
                        'source_id' => $payment->id,
                    ],
                    [
                        'gym_id' => $payment->gym_id,
                        'branch_id' => $payment->branch_id,
                        'created_by_user_id' => $payment->received_by_user_id ?: $payment->collected_by,
                        'entry_type' => 'membership_collection',
                        'direction' => 'inflow',
                        'category' => 'member_payment',
                        'title' => 'Membership payment',
                        'description' => $payment->notes,
                        'reference' => $payment->receipt_number ?: $payment->external_reference,
                        'payment_mode' => $payment->payment_mode,
                        'amount' => $payment->amount,
                        'status' => $payment->status === PaymentRecordStatus::Recorded->value ? 'posted' : 'reversed',
                        'occurred_at' => $payment->paid_at ?: $payment->payment_date ?: $payment->created_at ?: now(),
                        'metadata' => json_encode([
                            'payment_status' => $payment->payment_status,
                            'member_id' => $payment->member_id,
                            'membership_id' => $payment->member_membership_id,
                        ], JSON_THROW_ON_ERROR),
                        'created_at' => $payment->created_at ?: now(),
                        'updated_at' => now(),
                    ],
                );
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('gym_ledger_entries');
    }
};
