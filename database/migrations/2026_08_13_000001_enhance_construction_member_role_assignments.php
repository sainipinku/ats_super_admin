<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The project_id FK is backed by the (project_id, member_id) unique index,
        // so drop the FK first, swap the unique index, then restore the FK.
        Schema::table('construction_project_team_members', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique('construction_project_team_unique');
        });

        Schema::table('construction_project_team_members', function (Blueprint $table) {
            $table->unique(
                ['project_id', 'member_id', 'role_id'],
                'construction_project_team_role_unique'
            );
            $table->foreign('project_id')
                ->references('id')
                ->on('construction_projects')
                ->cascadeOnDelete();
        });

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->string('status')->default('active')->after('project_id');
        });

        Schema::table('construction_role_permissions', function (Blueprint $table) {
            $table->string('surface')->default('both')->after('permission_id');
        });
    }

    public function down(): void
    {
        Schema::table('construction_role_permissions', function (Blueprint $table) {
            $table->dropColumn('surface');
        });

        Schema::table('construction_member_role_assignments', function (Blueprint $table) {
            $table->dropColumn('status');
        });

        Schema::table('construction_project_team_members', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropUnique('construction_project_team_role_unique');
            $table->unique(['project_id', 'member_id'], 'construction_project_team_unique');
            $table->foreign('project_id')
                ->references('id')
                ->on('construction_projects')
                ->cascadeOnDelete();
        });
    }
};