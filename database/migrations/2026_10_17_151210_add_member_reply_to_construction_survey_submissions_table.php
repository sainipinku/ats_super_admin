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
        Schema::table('construction_survey_submissions', function (Blueprint $table) {
            if (!Schema::hasColumn('construction_survey_submissions', 'member_reply')) {
                $table->text('member_reply')->nullable()->after('review_notes');
            }
            if (!Schema::hasColumn('construction_survey_submissions', 'member_replied_at')) {
                $table->timestamp('member_replied_at')->nullable()->after('member_reply');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('construction_survey_submissions', function (Blueprint $table) {
            if (Schema::hasColumn('construction_survey_submissions', 'member_replied_at')) {
                $table->dropColumn('member_replied_at');
            }
            if (Schema::hasColumn('construction_survey_submissions', 'member_reply')) {
                $table->dropColumn('member_reply');
            }
        });
    }
};
