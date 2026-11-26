<?php

namespace Tests\Unit;

use App\Http\Controllers\SuperAdmin\EmployeeController;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeRoleSyncUnitTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalize_role_slugs_normalizes_aliases_ids_and_duplicates(): void
    {
        $normalized = EmployeeController::normalizeRoleSlugs([
            'Driver',
            '6',
            'vehicle_driver',
            'Survey Man',
            '4',
            'Draft Man',
            '5',
            'site_manager',
            '',
            null,
        ]);

        $this->assertSame([
            'vehicle_driver',
            'surveyor',
            'draft_person',
            'site_manager',
        ], $normalized);
    }

    public function test_member_roles_attribute_does_not_resurrect_deleted_driver_role_from_designation(): void
    {
        Role::firstOrCreate(['id' => 1, 'slug' => 'member'], ['name' => 'Member', 'is_default' => 1, 'created_by' => 1]);
        Role::firstOrCreate(['id' => 6, 'slug' => 'vehicle_driver'], ['name' => 'Vehicle Driver', 'is_default' => 1, 'created_by' => 1]);

        $driverConstructionRole = ConstructionRole::create([
            'company_id' => null,
            'name' => 'Vehicle Driver',
            'slug' => 'vehicle_driver',
            'status' => 'active',
            'is_system_role' => true,
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pinku',
            'username' => 'pinku_unit',
            'slug' => 'pinku-unit-' . Str::random(4),
            'email' => 'pinku_unit@example.com',
            'phone' => '9521343393',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Operations'],
            'designation' => ['Driver'],
            'roles' => [1],
            'created_by' => 1,
            'password' => 'password',
        ]);

        // Role assignment exists with status = 0 (explicitly removed/deactivated)
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driverConstructionRole->id,
            'status' => 0,
        ]);

        $fresh = $member->fresh();

        $this->assertSame([1], $fresh->roles);
        $this->assertFalse($fresh->isDriver());
    }

    public function test_employee_list_and_modals_send_member_base_role_and_render_role_validation_errors(): void
    {
        $listCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Employees/List.jsx'));
        $formModalCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Employees/Components/EmployeeFormModal.jsx'));
        $assignModalCode = file_get_contents(resource_path('js/Pages/SuperAdmin/Employees/Components/AssignRoleModal.jsx'));

        $this->assertStringNotContainsString(
            'role: member.role_slug || "member"',
            $listCode,
            'List.jsx must not send employee sub-role slug as base role field'
        );
        $this->assertStringContainsString('role: "member"', $listCode);

        $this->assertStringContainsString('roles.', $formModalCode, 'EmployeeFormModal must surface nested roles.* validation errors');
        $this->assertStringContainsString('errors', $assignModalCode);
    }
}
