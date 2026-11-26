<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assignment_role')) {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->string('assignment_role', 50)->nullable()->default(null)->change();
            });
        }

        if (Schema::hasTable('construction_execution_task_assignees') && Schema::hasColumn('construction_execution_task_assignees', 'assignment_role')) {
            Schema::table('construction_execution_task_assignees', function (Blueprint $table) {
                $table->string('assignment_role', 255)->nullable()->default(null)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assignment_role')) {
            DB::table('task_assignments')
                ->whereNull('assignment_role')
                ->update(['assignment_role' => 'executor']);

            Schema::table('task_assignments', function (Blueprint $table) {
                $table->string('assignment_role', 50)->nullable(false)->default('executor')->change();
            });
        }

        if (Schema::hasTable('construction_execution_task_assignees') && Schema::hasColumn('construction_execution_task_assignees', 'assignment_role')) {
            DB::table('construction_execution_task_assignees')
                ->whereNull('assignment_role')
                ->update(['assignment_role' => 'worker']);

            Schema::table('construction_execution_task_assignees', function (Blueprint $table) {
                $table->string('assignment_role', 255)->nullable(false)->default('worker')->change();
            });
        }
    }
};
