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
     * In the original migration, `assigned_by` was created as NOT NULL.
     * With polymorphic assignment tracking (`assigned_by_type` and `assigned_by_uid`),
     * `assigned_by` should be nullable to prevent SQL 1364 errors when omitted.
     */
    public function up(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assigned_by')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `task_assignments` MODIFY `assigned_by` BIGINT UNSIGNED NULL DEFAULT NULL');
            } else {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->unsignedBigInteger('assigned_by')->nullable()->default(null)->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('task_assignments') && Schema::hasColumn('task_assignments', 'assigned_by')) {
            $driver = DB::getDriverName();
            if ($driver === 'mysql') {
                DB::statement('ALTER TABLE `task_assignments` MODIFY `assigned_by` BIGINT UNSIGNED NOT NULL');
            } else {
                Schema::table('task_assignments', function (Blueprint $table) {
                    $table->unsignedBigInteger('assigned_by')->nullable(false)->change();
                });
            }
        }
    }
};
