<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('independent_trainer_member_relationships')) {
            Schema::create('independent_trainer_member_relationships', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('member_user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('invited_email');
                $table->boolean('is_current')->nullable()->default(true);
                $table->string('status')->default('pending');
                $table->json('sharing_permissions')->nullable();
                $table->timestamp('accepted_at')->nullable();
                $table->timestamp('declined_at')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->foreignId('revoked_by_user_id')->nullable();
                $table->string('revocation_reason', 500)->nullable();
                $table->timestamps();

                // MySQL limits identifiers to 64 characters, so this foreign key
                // cannot use Laravel's generated table-and-column based name.
                $table->foreign('revoked_by_user_id', 'independent_relationship_revoker_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->unique(['trainer_user_id', 'invited_email', 'is_current'], 'independent_trainer_member_current_unique');
                $table->index(['trainer_user_id', 'status'], 'independent_trainer_relationship_status_idx');
                $table->index(['member_user_id', 'status'], 'independent_member_relationship_status_idx');
            });
        } else {
            // MySQL may retain the table after a failed ALTER TABLE. Repair the
            // exact objects that were not created so `migrate --force` is safe to rerun.
            $this->repairPartiallyCreatedRelationshipsTable();
        }

        if (! Schema::hasTable('independent_trainer_member_invitations')) {
            Schema::create('independent_trainer_member_invitations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('relationship_id')->constrained('independent_trainer_member_relationships')->cascadeOnDelete();
                $table->uuid('token')->unique();
                $table->foreignId('trainer_user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('invited_user_id')->nullable()->constrained('users')->cascadeOnDelete();
                $table->string('invited_name');
                $table->string('invited_email');
                $table->foreignId('invited_by_user_id');
                $table->string('status')->default('pending');
                $table->json('payload')->nullable();
                $table->timestamp('expires_at');
                $table->timestamp('responded_at')->nullable();
                $table->timestamps();

                $table->foreign('invited_by_user_id', 'independent_invitation_inviter_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
                $table->index(['invited_user_id', 'status'], 'independent_invites_user_status_idx');
                $table->index(['trainer_user_id', 'status'], 'independent_invites_trainer_status_idx');
                $table->index(['invited_email', 'status'], 'independent_invites_email_status_idx');
            });
        } else {
            $this->repairPartiallyCreatedInvitationsTable();
        }
    }

    private function repairPartiallyCreatedRelationshipsTable(): void
    {
        if (! Schema::hasForeignKey('independent_trainer_member_relationships', ['revoked_by_user_id'])) {
            Schema::table('independent_trainer_member_relationships', function (Blueprint $table): void {
                $table->foreign('revoked_by_user_id', 'independent_relationship_revoker_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
            });
        }

        if (! Schema::hasIndex('independent_trainer_member_relationships', 'independent_trainer_member_current_unique')) {
            Schema::table('independent_trainer_member_relationships', function (Blueprint $table): void {
                $table->unique(['trainer_user_id', 'invited_email', 'is_current'], 'independent_trainer_member_current_unique');
            });
        }

        if (! Schema::hasIndex('independent_trainer_member_relationships', 'independent_trainer_relationship_status_idx')) {
            Schema::table('independent_trainer_member_relationships', function (Blueprint $table): void {
                $table->index(['trainer_user_id', 'status'], 'independent_trainer_relationship_status_idx');
            });
        }

        if (! Schema::hasIndex('independent_trainer_member_relationships', 'independent_member_relationship_status_idx')) {
            Schema::table('independent_trainer_member_relationships', function (Blueprint $table): void {
                $table->index(['member_user_id', 'status'], 'independent_member_relationship_status_idx');
            });
        }
    }

    private function repairPartiallyCreatedInvitationsTable(): void
    {
        if (! Schema::hasForeignKey('independent_trainer_member_invitations', ['invited_by_user_id'])) {
            Schema::table('independent_trainer_member_invitations', function (Blueprint $table): void {
                $table->foreign('invited_by_user_id', 'independent_invitation_inviter_fk')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasIndex('independent_trainer_member_invitations', ['token'], 'unique')) {
            Schema::table('independent_trainer_member_invitations', function (Blueprint $table): void {
                $table->unique('token');
            });
        }

        foreach ([
            'independent_invites_user_status_idx' => ['invited_user_id', 'status'],
            'independent_invites_trainer_status_idx' => ['trainer_user_id', 'status'],
            'independent_invites_email_status_idx' => ['invited_email', 'status'],
        ] as $name => $columns) {
            if (! Schema::hasIndex('independent_trainer_member_invitations', $name)) {
                Schema::table('independent_trainer_member_invitations', function (Blueprint $table) use ($columns, $name): void {
                    $table->index($columns, $name);
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('independent_trainer_member_invitations');
        Schema::dropIfExists('independent_trainer_member_relationships');
    }
};
