<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->boolean('is_senior')
                ->default(false)
                ->after('project_id')
                ->comment('Senior Draftsman can skip junior review in Phase 4');

            $table->boolean('can_self_draft')
                ->default(false)
                ->after('is_senior')
                ->comment('Skilled Surveyor can perform drafting tasks directly in field');

            $table->index('is_senior', 'cmra_is_senior_idx');
            $table->index('can_self_draft', 'cmra_can_self_draft_idx');
        });

        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            $table->text('rejection_reason')->nullable()->after('status');
            $table->foreignId('rejected_by_member_id')
                ->nullable()
                ->after('rejection_reason')
                ->constrained('members')
                ->nullOnDelete();
            $table->timestamp('rejected_at')->nullable()->after('rejected_by_member_id');
            $table->unsignedInteger('rejection_count')->default(0)->after('rejected_at');
        });

        Schema::table('construction_drawing_approvals', function (Blueprint $table) {
            $table->boolean('skip_junior_review')
                ->default(false)
                ->after('remarks')
                ->comment('True when Senior Draftsman submits — goes straight to senior approval');

            $table->enum('review_level', ['junior', 'senior', 'final'])
                ->default('junior')
                ->after('skip_junior_review');

            $table->index('skip_junior_review', 'cda_skip_junior_idx');
            $table->index(['review_level', 'decision'], 'cda_level_decision_idx');
        });
    }

    public function down(): void
    {
        Schema::table('construction_drawing_approvals', function (Blueprint $table) {
            $table->dropIndex('cda_level_decision_idx');
            $table->dropIndex('cda_skip_junior_idx');

            $table->dropColumn(['skip_junior_review', 'review_level']);
        });

        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejected_by_member_id');
            $table->dropColumn(['rejection_reason', 'rejected_at', 'rejection_count']);
        });

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->dropIndex('cmra_can_self_draft_idx');
            $table->dropIndex('cmra_is_senior_idx');

            $table->dropColumn(['is_senior', 'can_self_draft']);
        });
    }
};
