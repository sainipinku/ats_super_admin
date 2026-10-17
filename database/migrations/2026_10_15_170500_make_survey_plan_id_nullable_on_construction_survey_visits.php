<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Field surveyors submitting daily visits directly linked to an execution task
     * or project might not always have a pre-configured survey plan.
     * Making survey_plan_id nullable prevents 1048 integrity constraint violations.
     */
    public function up(): void
    {
        if (Schema::hasTable('construction_survey_visits') && Schema::hasColumn('construction_survey_visits', 'survey_plan_id')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `construction_survey_visits` MODIFY `survey_plan_id` BIGINT UNSIGNED NULL');
            } else {
                Schema::table('construction_survey_visits', function (Blueprint $table) {
                    $table->unsignedBigInteger('survey_plan_id')->nullable()->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('construction_survey_visits') && Schema::hasColumn('construction_survey_visits', 'survey_plan_id')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `construction_survey_visits` MODIFY `survey_plan_id` BIGINT UNSIGNED NOT NULL');
            } else {
                Schema::table('construction_survey_visits', function (Blueprint $table) {
                    $table->unsignedBigInteger('survey_plan_id')->nullable(false)->change();
                });
            }
        }
    }
};
