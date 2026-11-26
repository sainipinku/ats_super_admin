<?php

namespace Database\Seeders;

use App\Models\ConstructionRole;
use App\Models\Permission;
use App\Services\Construction\CompanyRoleProvisioningService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DrawingApprovalAssignPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            [
                'name' => 'Assign CAD Approval to Admin',
                'slug' => 'drawing_approval.assign_admin',
                'module' => 'Project Lifecycle',
                'description' => 'Assign submitted CAD drawing approvals to an admin or super admin of the company',
            ],
            [
                'name' => 'View Drawing Approvals',
                'slug' => 'drawing_approval.view',
                'module' => 'Project Lifecycle',
                'description' => 'View drawing approval queue',
            ],
            [
                'name' => 'Approve Drawing',
                'slug' => 'drawing_approval.approve',
                'module' => 'Project Lifecycle',
                'description' => 'Approve CAD drawing',
            ],
        ];

        $permissionIds = [];
        foreach ($permissions as $index => $permData) {
            if ($index === 0) {
                $perm = Permission::updateOrCreate(['slug' => $permData['slug']], $permData);
            } else {
                $perm = Permission::firstOrCreate(['slug' => $permData['slug']], $permData);
            }
            $permissionIds[] = $perm->id;
        }

        // Ensure global baseline roles exist if seeded fresh
        ConstructionRole::firstOrCreate(
            ['slug' => 'super_admin', 'company_id' => null],
            [
                'name' => 'Super Admin',
                'description' => 'Full System Access',
                'is_system_role' => true,
                'status' => 'active',
            ]
        );

        ConstructionRole::firstOrCreate(
            ['slug' => 'project_admin', 'company_id' => null],
            [
                'name' => 'Project Admin',
                'description' => 'Project Administration Access',
                'is_system_role' => true,
                'status' => 'active',
            ]
        );

        $targetRoles = ConstructionRole::whereIn('slug', [
            'super_admin',
            'superadmin',
            'admin',
            'project_admin',
        ])->get();

        foreach ($targetRoles as $role) {
            foreach ($permissionIds as $permId) {
                DB::table('construction_role_permissions')->updateOrInsert(
                    [
                        'role_id' => $role->id,
                        'permission_id' => $permId,
                    ],
                    [
                        'surface' => 'both',
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }

        // Keep all existing & future company Super Admin, Admin & Project Admin roles synchronized
        if (class_exists(CompanyRoleProvisioningService::class)) {
            app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();
        }
    }
}
