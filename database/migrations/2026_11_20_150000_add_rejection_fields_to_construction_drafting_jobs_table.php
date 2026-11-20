<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('construction_drafting_jobs')) {
            return;
        }

        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_drafting_jobs', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('status');
            }
            if (!Schema::hasColumn('construction_drafting_jobs', 'rejected_by_member_id')) {
                $table->foreignId('rejected_by_member_id')->nullable()->after('rejection_reason')->constrained('members')->nullOnDelete();
            }
            if (!Schema::hasColumn('construction_drafting_jobs', 'rejected_at')) {
                $table->timestamp('rejected_at')->nullable()->after('rejected_by_member_id');
            }
            if (!Schema::hasColumn('construction_drafting_jobs', 'rejection_count')) {
                $table->unsignedInteger('rejection_count')->default(0)->after('rejected_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('construction_drafting_jobs')) {
            return;
        }

        Schema::table('construction_drafting_jobs', function (Blueprint $table) {
            if (Schema::hasColumn('construction_drafting_jobs', 'rejected_by_member_id')) {
                $table->dropForeign(['rejected_by_member_id']);
                $table->dropColumn('rejected_by_member_id');
            }
            if (Schema::hasColumn('construction_drafting_jobs', 'rejection_reason')) {
                $table->dropColumn('rejection_reason');
            }
            if (Schema::hasColumn('construction_drafting_jobs', 'rejected_at')) {
                $table->dropColumn('rejected_at');
            }
            if (Schema::hasColumn('construction_drafting_jobs', 'rejection_count')) {
                $table->dropColumn('rejection_count');
            }
        });
    }
};
