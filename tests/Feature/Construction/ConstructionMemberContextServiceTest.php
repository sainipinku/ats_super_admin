<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\MemberRoleAssignment;
use App\Models\Construction\Permission;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use App\Models\Member;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionMemberContextService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConstructionMemberContextServiceTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Construction Super Admin',
            'email' => 'super-admin@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(
        string $projectCode = 'PRJ-001',
        string $slug = 'project-001',
        string $name = 'Construction Project'
    ): Project {
        $company = Company::create([
            'name' => $name . ' Company',
            'status' => 'active',
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $projectCode . '-CLIENT',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $projectCode,
            'name' => $name,
            'slug' => $slug,
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);
    }

    private function createRole(string $slug, string $name = 'Role'): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'is_system_role' => true,
            'status' => 'active',
        ]);
    }

    private function createPermission(string $slug): Permission
    {
        return Permission::create([
            'name' => $slug,
            'slug' => $slug,
            'module' => 'test',
        ]);
    }

    private function assignPermission(Role $role, Permission $permission): void
    {
        $role->permissions()->attach($permission->id);
    }

    private function service(): ConstructionMemberContextService
    {
        return app(ConstructionMemberContextService::class);
    }

    public function test_single_role_member_with_single_project_auto_selects_context(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Single Context');
        $project = $this->createProject('PRJ-C1', 'project-c1', 'Context Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($surveyor, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'status' => 'active',
        ]);

        $context = $this->service()->getMobileContext($member);

        $this->assertSame($project->id, $context['active_project']->id);
        $this->assertSame('surveyor', $context['active_role']->slug);
        $this->assertSame(['survey.create'], $context['permissions']);
    }

    public function test_project_specific_role_auto_selects_single_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Auto Project Role');
        $project = $this->createProject('PRJ-AP', 'project-ap', 'Auto Project');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $tripStart = $this->createPermission('vehicle.trip.start');
        $this->assignPermission($driver, $tripStart);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'status' => 'active',
        ]);

        $context = $this->service()->getMobileContext($member, 'vehicle_driver');

        $this->assertSame($project->id, $context['active_project']->id);
        $this->assertSame('vehicle_driver', $context['active_role']->slug);
        $this->assertSame(['vehicle.trip.start'], $context['permissions']);
    }

    public function test_project_specific_role_with_multiple_projects_requires_selection(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Project Driver');
        $projectA = $this->createProject('PRJ-MPD1', 'project-mpd1', 'Multi Project Driver A');
        $projectB = $this->createProject('PRJ-MPD2', 'project-mpd2', 'Multi Project Driver B');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectB->id,
            'status' => 1,
        ]);

        // Project-specific role cannot resolve without an explicit project.
        $this->expectException(AuthorizationException::class);
        $this->service()->getMobileContext($member, 'vehicle_driver');
    }

    public function test_multiple_projects_require_selection(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Project');
        $projectA = $this->createProject('PRJ-MA', 'project-ma', 'Multi Project A');
        $projectB = $this->createProject('PRJ-MB', 'project-mb', 'Multi Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        foreach ([[$projectA, $surveyor], [$projectB, $driver]] as [$project, $role]) {
            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $role->id,
                'project_id' => $project->id,
                'status' => 1,
            ]);
            ProjectTeamMember::create([
                'project_id' => $project->id,
                'member_id' => $member->id,
                'role_id' => $role->id,
                'status' => 'active',
            ]);
        }

        $context = $this->service()->getMobileContext($member);

        $this->assertNull($context['active_project']);
        $this->assertNull($context['active_role']);
        $this->assertSame([], $context['permissions']);
        $this->assertCount(2, $context['projects']);
    }

    public function test_global_role_without_project_is_allowed(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Global Role');
        $reviewer = $this->createRole('review_approver', 'Review Approver');
        $review = $this->createPermission('survey_submission.review');
        $this->assignPermission($reviewer, $review);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $reviewer->id,
            'project_id' => null,
            'status' => 1,
        ]);

        $context = $this->service()->getWebContext($member, 'review_approver');

        $this->assertNull($context['active_project']);
        $this->assertSame('review_approver', $context['active_role']->slug);
        $this->assertSame(['survey_submission.review'], $context['permissions']);
    }

    public function test_single_global_role_is_auto_selected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Auto Global');
        $reviewer = $this->createRole('review_approver', 'Review Approver');
        $review = $this->createPermission('survey_submission.review');
        $this->assignPermission($reviewer, $review);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $reviewer->id,
            'project_id' => null,
            'status' => 1,
        ]);

        $context = $this->service()->getWebContext($member);

        $this->assertNull($context['active_project']);
        $this->assertSame('review_approver', $context['active_role']->slug);
        $this->assertSame(['survey_submission.review'], $context['permissions']);
    }

    public function test_requested_project_plus_driver_returns_driver_permissions(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Driver Project');
        $projectA = $this->createProject('PRJ-DP', 'project-dp', 'Driver Project');
        $projectB = $this->createProject('PRJ-DPB', 'project-dpb', 'Driver Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $tripStart = $this->createPermission('vehicle.trip.start');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($driver, $tripStart);
        $this->assignPermission($surveyor, $surveyCreate);

        foreach (['surveyor' => $surveyor, 'vehicle_driver' => $driver] as $slug => $role) {
            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $role->id,
                'project_id' => $projectA->id,
                'status' => 1,
            ]);
        }
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectB->id,
            'status' => 1,
        ]);

        $context = $this->service()->getMobileContext($member, 'vehicle_driver', $projectA->id);

        $this->assertSame($projectA->id, $context['active_project']->id);
        $this->assertSame('vehicle_driver', $context['active_role']->slug);
        $this->assertSame(['vehicle.trip.start'], $context['permissions']);
    }

    public function test_requested_project_plus_surveyor_returns_surveyor_permissions(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Surveyor Project');
        $project = $this->createProject('PRJ-SP', 'project-sp', 'Surveyor Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $tripStart = $this->createPermission('vehicle.trip.start');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($driver, $tripStart);
        $this->assignPermission($surveyor, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $context = $this->service()->getMobileContext($member, 'surveyor', $project->id);

        $this->assertSame('surveyor', $context['active_role']->slug);
        $this->assertSame(['survey.create'], $context['permissions']);
        $this->assertNotContains('vehicle.trip.start', $context['permissions']);
    }

    public function test_role_from_another_project_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Project Role');
        $projectA = $this->createProject('PRJ-WPA', 'project-wpa', 'Wrong Project A');
        $projectB = $this->createProject('PRJ-WPB', 'project-wpb', 'Wrong Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectB->id,
            'status' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service()->getMobileContext($member, 'vehicle_driver', $projectA->id);
    }

    public function test_invalid_project_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Invalid Project');
        $project = $this->createProject('PRJ-IP', 'project-ip', 'Invalid Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service()->getMobileContext($member, 'surveyor', 999999);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Invalid Role');
        $project = $this->createProject('PRJ-IR', 'project-ir', 'Invalid Role Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service()->getMobileContext($member, 'fake_role', $project->id);
    }

    public function test_common_permission_model_shared_across_surfaces(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Common Permission');
        $project = $this->createProject('PRJ-CP', 'project-cp', 'Common Permission Project');
        $role = $this->createRole('site_employee', 'Site Employee');
        $attendanceMark = $this->createPermission('attendance.mark');
        $dashboardView = $this->createPermission('dashboard.view');
        $this->assignPermission($role, $attendanceMark);
        $this->assignPermission($role, $dashboardView);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 1,
        ]);

        // The same permission set is returned for both web and mobile contexts.
        $webContext = $this->service()->getWebContext($member, 'site_employee', $project->id);
        $mobileContext = $this->service()->getMobileContext($member, 'site_employee', $project->id);

        $this->assertSame(['attendance.mark', 'dashboard.view'], $webContext['permissions']);
        $this->assertSame(['attendance.mark', 'dashboard.view'], $mobileContext['permissions']);
    }

    public function test_multiple_roles_same_project_require_selection(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Role Same Project');
        $project = $this->createProject('PRJ-MRS', 'project-mrs', 'Multi Role Same Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        foreach ([$surveyor, $driver] as $role) {
            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $role->id,
                'project_id' => $project->id,
                'status' => 1,
            ]);
        }

        $context = $this->service()->getMobileContext($member);

        $this->assertSame($project->id, $context['active_project']?->id);
        $this->assertNull($context['active_role']);
        $this->assertSame([], $context['permissions']);
    }

    public function test_inactive_role_assignment_is_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Context Role');
        $project = $this->createProject('PRJ-ICR', 'project-icr', 'Inactive Context Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $surveyCreate = $this->createPermission('survey.create');
        $this->assignPermission($surveyor, $surveyCreate);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 0,
        ]);

        $this->expectException(AuthorizationException::class);
        $this->service()->getMobileContext($member, 'surveyor', $project->id);
    }

    public function test_multiple_projects_returns_available_context_clearly(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Context Distinction');
        $projectA = $this->createProject('PRJ-CD1', 'project-cd1', 'Context Project A');
        $projectB = $this->createProject('PRJ-CD2', 'project-cd2', 'Context Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectA->id,
            'status' => 1,
        ]);
        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectB->id,
            'status' => 1,
        ]);

        $context = $this->service()->getMobileContext($member, 'vehicle_driver', $projectB->id);

        $this->assertSame($projectB->id, $context['active_project']->id);
        $this->assertSame('vehicle_driver', $context['active_role']->slug);
        $this->assertCount(2, $context['roles']);
        $this->assertCount(2, $context['projects']);
    }
}