<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('construction_survey_visits')) {
            Schema::table('construction_survey_visits', function (Blueprint $table) {
                if (!Schema::hasColumn('construction_survey_visits', 'execution_task_id')) {
                    $table->unsignedBigInteger('execution_task_id')->nullable()->after('task_id')->index();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('construction_survey_visits')) {
            Schema::table('construction_survey_visits', function (Blueprint $table) {
                if (Schema::hasColumn('construction_survey_visits', 'execution_task_id')) {
                    $table->dropColumn('execution_task_id');
                }
            });
        }
    }
};
