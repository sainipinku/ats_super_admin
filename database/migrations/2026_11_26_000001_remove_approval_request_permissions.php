<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('construction_permissions')) {
            return;
        }

        $permissionIds = DB::table('construction_permissions')
            ->where('slug', 'like', 'approval_request.%')
            ->pluck('id');

        if ($permissionIds->isNotEmpty() && Schema::hasTable('construction_role_permissions')) {
            DB::table('construction_role_permissions')
                ->whereIn('permission_id', $permissionIds)
                ->delete();
        }

        DB::table('construction_permissions')
            ->where('slug', 'like', 'approval_request.%')
            ->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('construction_permissions')) {
            return;
        }

        $now = now();
        $permissions = [
            ['name' => 'View Permission Requests', 'slug' => 'approval_request.view', 'module' => 'Overview', 'description' => 'View pending role and permission requests'],
            ['name' => 'Approve Permission Request', 'slug' => 'approval_request.approve', 'module' => 'Overview', 'description' => 'Approve user requested permissions'],
            ['name' => 'Reject Permission Request', 'slug' => 'approval_request.reject', 'module' => 'Overview', 'description' => 'Reject user requested permissions'],
        ];

        foreach ($permissions as $perm) {
            $exists = DB::table('construction_permissions')->where('slug', $perm['slug'])->exists();
            if (!$exists) {
                DB::table('construction_permissions')->insert(array_merge($perm, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
};
