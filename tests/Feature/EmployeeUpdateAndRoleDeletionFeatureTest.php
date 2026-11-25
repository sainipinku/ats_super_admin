<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Employee;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Services\Construction\CompanyRoleProvisioningService;
use App\Services\Construction\ConstructionAuthorizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeUpdateAndRoleDeletionFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $companyAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        Permission::firstOrCreate(
            ['slug' => 'vehicle.manage'],
            ['name' => 'Manage Vehicles', 'module' => 'fleet']
        );
        Permission::firstOrCreate(
            ['slug' => 'survey.manage'],
            ['name' => 'Manage Surveys', 'module' => 'survey']
        );

        Role::firstOrCreate(['id' => 1, 'slug' => 'member'], ['name' => 'Member', 'is_default' => 1, 'status' => 1, 'created_by' => 1]);
        Role::firstOrCreate(['id' => 4, 'slug' => 'surveyor'], ['name' => 'Surveyor', 'is_default' => 1, 'status' => 1, 'created_by' => 1]);
        Role::firstOrCreate(['id' => 6, 'slug' => 'vehicle_driver'], ['name' => 'Vehicle Driver', 'is_default' => 1, 'status' => 1, 'created_by' => 1]);

        $this->company = Company::create([
            'name' => 'Track Survey Infra',
            'status' => 'active',
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($this->company->id);

        // Create root platform owner (id = 1) first so $this->companyAdmin is a company SuperAdmin (id = 2)
        SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Root Owner',
            'email' => 'root-owner@example.com',
            'phone' => '9876500000',
            'whatsapp_phone' => '9876500000',
            'password' => Hash::make('password'),
            'status' => 1,
            'company_id' => null,
            'roles' => ['super_admin'],
        ]);

        $this->companyAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Company SuperAdmin',
            'email' => 'company-sa@example.com',
            'phone' => '9876500001',
            'whatsapp_phone' => '9876500001',
            'password' => Hash::make('password'),
            'status' => 1,
            'company_id' => $this->company->id,
            'roles' => ['super_admin'],
        ]);
    }

    private function createEmployeeWithRoles(array $roleRecords, string $department = 'Survey', string $designation = 'Surveyor'): Employee
    {
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pinku',
            'username' => 'pinku_' . Str::lower(Str::random(4)),
            'slug' => 'pinku-' . Str::lower(Str::random(4)),
            'email' => 'pinku_' . Str::lower(Str::random(4)) . '@example.com',
            'phone' => '95213' . random_int(10000, 99999),
            'status' => Member::STATUS_ACTIVE,
            'departments' => [$department],
            'designation' => [$designation],
            'roles' => [4, 6],
            'company_name' => $this->company->name,
            'created_by' => $this->companyAdmin->id,
            'password' => Hash::make('password'),
        ]);

        foreach ($roleRecords as $cRole) {
            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $cRole->id,
                'status' => 1,
            ]);
        }

        return Employee::create([
            'uuid' => (string) Str::uuid(),
            'member_id' => $member->id,
            'employee_id' => 'EMP-' . random_int(100000, 999999),
            'aadhaar_number' => '868768767868',
        ]);
    }

    public function test_employee_update_removes_unchecked_global_or_company_driver_role_and_updates_details(): void
    {
        $companySurveyor = ConstructionRole::where('company_id', $this->company->id)
            ->where('slug', 'surveyor')
            ->firstOrFail();

        // Simulate legacy/mismatched assignment pointing to global template role (company_id = null)
        $globalDriver = ConstructionRole::whereNull('company_id')
            ->where('slug', 'vehicle_driver')
            ->firstOrFail();

        $vehiclePerm = Permission::where('slug', 'vehicle.manage')->firstOrFail();
        $globalDriver->permissions()->syncWithoutDetaching([$vehiclePerm->id]);

        $employee = $this->createEmployeeWithRoles([$companySurveyor, $globalDriver], 'Operations', 'Driver');
        $member = $employee->member;

        $response = $this->actingAs($this->companyAdmin, 'superadmin')
            ->put(route('super.employees.update', $employee->uuid), [
                'full_name' => 'Pinku Updated',
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => 'member',
                'roles' => ['surveyor'],
                'department' => 'Survey',
                'designation' => 'Surveyor',
                'gender' => 'male',
                'dob' => '2009-09-06',
                'status' => 1,
                'aadhaar_number' => '868768767868',
            ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $freshMember = $member->fresh();
        $this->assertSame('Pinku Updated', $freshMember->name);
        $this->assertSame(['Survey'], $freshMember->departments);
        $this->assertSame(['Surveyor'], $freshMember->designation);

        // Global Driver role assignment MUST be deactivated (status = 0), Surveyor remains active
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $companySurveyor->id,
            'status' => 1,
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $globalDriver->id,
            'status' => 0,
        ]);

        $expectedSurveyorRoleId = (int) Role::where('slug', 'surveyor')->value('id');
        $this->assertSame([$expectedSurveyorRoleId], $freshMember->roles);
        $this->assertSame(['surveyor'], $freshMember->assigned_roles);
        $this->assertFalse($freshMember->isDriver());

        \Illuminate\Support\Facades\Auth::guard('superadmin')->logout();
        session()->flush();

        $perms = app(ConstructionAuthorizationService::class)->permissionsFor($freshMember);
        $this->assertNotContains('vehicle.manage', $perms);

        // Verify SuperAdmin Employees list Inertia response no longer lists Driver for this employee
        $listResp = $this->actingAs($this->companyAdmin, 'superadmin')
            ->get(route('super.employees.list'));
        $listResp->assertOk();
        $rows = $listResp->original->getData()['page']['props']['employees']['data'] ?? [];
        $updatedRow = collect($rows)->firstWhere('id', $employee->id);
        $this->assertNotNull($updatedRow);
        $this->assertSame(['surveyor'], $updatedRow['member']['assigned_roles']);
        $this->assertNotContains('Driver', $updatedRow['member']['assigned_role_names']);
    }

    public function test_assign_role_endpoint_deletes_removed_roles_and_allows_clearing_all_roles_even_with_driver_designation(): void
    {
        $companySurveyor = ConstructionRole::where('company_id', $this->company->id)
            ->where('slug', 'surveyor')
            ->firstOrFail();
        $companyDriver = ConstructionRole::where('company_id', $this->company->id)
            ->where('slug', 'vehicle_driver')
            ->firstOrFail();

        $employee = $this->createEmployeeWithRoles([$companySurveyor, $companyDriver], 'Operations', 'Driver');
        $member = $employee->member;

        // 1. Remove Driver, keep only Surveyor
        $resp1 = $this->actingAs($this->companyAdmin, 'superadmin')
            ->post(route('super.employees.assign_role', $employee->uuid), [
                'roles' => ['surveyor'],
            ]);

        $resp1->assertRedirect();
        $resp1->assertSessionHasNoErrors();

        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $companyDriver->id,
            'status' => 0,
        ]);
        $this->assertSame(['surveyor'], $member->fresh()->assigned_roles);

        // 2. Remove ALL roles (even though member designation is 'Driver')
        $resp2 = $this->actingAs($this->companyAdmin, 'superadmin')
            ->post(route('super.employees.assign_role', $employee->uuid), [
                'roles' => [],
            ]);

        $resp2->assertRedirect();
        $resp2->assertSessionHasNoErrors();

        $this->assertSame(0, MemberRoleAssignment::where('member_id', $member->id)->where('status', 1)->count());
        $freshMember = $member->fresh();
        $this->assertSame([1], $freshMember->roles);
        $this->assertFalse($freshMember->isDriver());
    }

    public function test_employee_update_supports_and_removes_custom_roles_from_roles_table(): void
    {
        // Custom role exists in `roles` table (e.g. created in Role Management without pre-existing construction_roles row)
        $customRole = Role::create([
            'name' => 'Site Manager',
            'slug' => 'site_manager',
            'status' => 1,
            'is_default' => 0,
            'super_admin_id' => $this->companyAdmin->id,
            'created_by' => $this->companyAdmin->id,
        ]);

        $companySurveyor = ConstructionRole::where('company_id', $this->company->id)
            ->where('slug', 'surveyor')
            ->firstOrFail();

        $employee = $this->createEmployeeWithRoles([$companySurveyor], 'Survey', 'Surveyor');
        $member = $employee->member;

        // Assign custom role + surveyor via employee update
        $assignResp = $this->actingAs($this->companyAdmin, 'superadmin')
            ->put(route('super.employees.update', $employee->uuid), [
                'full_name' => 'Pinku',
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => 'member',
                'roles' => ['surveyor', $customRole->slug],
                'department' => 'Survey',
                'designation' => 'Surveyor',
            ]);

        $assignResp->assertRedirect();
        $assignResp->assertSessionHasNoErrors();
        $this->assertContains('site_manager', $member->fresh()->assigned_roles);

        // Remove custom role via employee update
        $removeResp = $this->actingAs($this->companyAdmin, 'superadmin')
            ->put(route('super.employees.update', $employee->uuid), [
                'full_name' => 'Pinku',
                'email' => $member->email,
                'phone' => $member->phone,
                'role' => 'member',
                'roles' => ['surveyor'],
                'department' => 'Survey',
                'designation' => 'Surveyor',
            ]);

        $removeResp->assertRedirect();
        $removeResp->assertSessionHasNoErrors();

        $freshRoles = $member->fresh()->assigned_roles;
        $this->assertContains('surveyor', $freshRoles);
        $this->assertNotContains('site_manager', $freshRoles);
    }
}
