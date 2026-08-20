<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
   
    public function up(): void
    {
        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->unsignedTinyInteger('status_tmp')
                ->default(1)
                ->after('project_id');
        });

        DB::table('construction_member_role_assignments')->update([
            'status_tmp' => DB::raw("
                CASE
                    WHEN status = 'active' THEN 1
                    WHEN status = 'inactive' THEN 0
                    WHEN status = '1' THEN 1
                    WHEN status = '0' THEN 0
                    ELSE 1
                END
            "),
        ]);

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->renameColumn('status_tmp', 'status');
        });
    }

    /**
     * Restore the original string status values.
     */
    public function down(): void
    {
        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->string('status_tmp')
                ->default('active')
                ->after('project_id');
        });

        DB::table('construction_member_role_assignments')->update([
            'status_tmp' => DB::raw("
                CASE
                    WHEN status = 1 THEN 'active'
                    WHEN status = 0 THEN 'inactive'
                    ELSE 'active'
                END
            "),
        ]);

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->renameColumn('status_tmp', 'status');
        });
    }
};