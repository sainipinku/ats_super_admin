<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('construction_survey_plan_members', function (Blueprint $table) {
            $table->foreignId('survey_team_id')
                ->nullable()
                ->after('survey_plan_id')
                ->constrained('construction_survey_teams')
                ->nullOnDelete();

            $table->enum('work_type', ['A', 'B'])
                ->nullable()
                ->after('role_in_survey')
                ->comment('A = Maint. Machine operator, B = Danda Pakden (physical marking)');

            $table->index('survey_team_id', 'cspm_team_idx');
            $table->index(['survey_plan_id', 'work_type'], 'cspm_plan_worktype_idx');
        });
    }

    public function down(): void
    {
        Schema::table('construction_survey_plan_members', function (Blueprint $table) {
            $table->dropIndex('cspm_plan_worktype_idx');
            $table->dropIndex('cspm_team_idx');

            $table->dropConstrainedForeignId('survey_team_id');
            $table->dropColumn(['work_type']);
        });
    }
};
