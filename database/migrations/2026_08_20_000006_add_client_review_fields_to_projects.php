<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_projects', function (Blueprint $table) {
            $table->enum('client_review_status', [
                'not_started',
                'pending_review',
                'revisions_required',
                'client_approved',
            ])->default('not_started')->after('current_stage');

            $table->timestamp('client_review_requested_at')->nullable()->after('client_review_status');
            $table->foreignId('client_review_requested_by')
                ->nullable()
                ->after('client_review_requested_at')
                ->constrained('members')
                ->nullOnDelete();

            $table->timestamp('client_approved_at')->nullable()->after('client_review_requested_by');
            $table->foreignId('client_approved_by')
                ->nullable()
                ->after('client_approved_at')
                ->constrained('construction_clients')
                ->nullOnDelete();

            $table->text('client_revision_comment')->nullable()->after('client_approved_by');
            $table->json('partial_revision_sections')->nullable()->after('client_revision_comment');

            $table->unsignedInteger('revision_iteration_count')->default(0)->after('partial_revision_sections');

            $table->index('client_review_status', 'cproj_client_review_status_idx');
            $table->index(['client_id', 'client_review_status'], 'cproj_client_status_idx');
        });

        Schema::create('construction_client_revision_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('construction_projects')
                ->cascadeOnDelete();

            $table->foreignId('client_id')
                ->nullable()
                ->constrained('construction_clients')
                ->nullOnDelete();

            $table->foreignId('assigned_supervisor_member_id')
                ->nullable()
                ->constrained('members')
                ->nullOnDelete();

            $table->enum('action', [
                'request_revision',
                'assigned_to_surveyor',
                'assigned_to_draftsman',
                'internal_fix_complete',
                'supervisor_verified',
                'resubmitted_for_client_review',
                'client_reapproved',
            ]);

            $table->unsignedInteger('revision_cycle_number')->default(1);
            $table->text('comment')->nullable();
            $table->json('affected_sections')->nullable();

            $table->nullableMorphs('actor', 'ccrl_actor_idx');
            $table->timestamp('action_at')->useCurrent();
            $table->timestamps();

            $table->index(['project_id', 'revision_cycle_number'], 'ccrl_project_cycle_idx');
            $table->index(['action', 'action_at'], 'ccrl_action_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('construction_client_revision_logs');

        Schema::table('construction_projects', function (Blueprint $table) {
            $table->dropIndex('cproj_client_status_idx');
            $table->dropIndex('cproj_client_review_status_idx');

            $table->dropConstrainedForeignId('client_review_requested_by');
            $table->dropConstrainedForeignId('client_approved_by');

            $table->dropColumn([
                'client_review_status',
                'client_review_requested_at',
                'client_approved_at',
                'client_revision_comment',
                'partial_revision_sections',
                'revision_iteration_count',
            ]);
        });
    }
};
