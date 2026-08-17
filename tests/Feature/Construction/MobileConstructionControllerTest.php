<?php

namespace Tests\Feature\Construction;

use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Permission;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MobileConstructionControllerTest extends TestCase
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

    private function assignRoleToProject(
        Member $member,
        Role $role,
        Project $project,
        array $permissionSlugs = [],
        string $surface = 'mobile'
    ): void {
        foreach ($permissionSlugs as $slug) {
            $role->permissions()->syncWithoutDetaching(
                [Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'test'])->id => ['surface' => $surface]]
            );
        }

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
    }

    private function assignGlobalRole(
        Member $member,
        Role $role,
        array $permissionSlugs = [],
        string $surface = 'mobile'
    ): void {
        foreach ($permissionSlugs as $slug) {
            $role->permissions()->syncWithoutDetaching(
                [Permission::firstOrCreate(['slug' => $slug], ['name' => $slug, 'module' => 'test'])->id => ['surface' => $surface]]
            );
        }

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => null,
            'status' => 'active',
        ]);
    }

    public function test_context_returns_valid_mobile_context(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Mobile Context Member');
        $project = $this->createProject('PRJ-MC', 'project-mc', 'Mobile Context');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view', 'survey.create']);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/construction/mobile/construction/context');

        $response->assertOk();
        $response->assertJsonPath('success', true);
        $response->assertJsonPath('data.active_project.id', $project->id);
        $response->assertJsonPath('data.active_role.slug', 'surveyor');
        $response->assertJsonCount(1, 'data.projects');
        $response->assertJsonCount(1, 'data.available_roles');
        $this->assertContains('survey.view', $response->json('data.permissions'));
        $this->assertContains('survey.create', $response->json('data.permissions'));
    }

    public function test_context_single_project_auto_selects(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Auto Select Member');
        $project = $this->createProject('PRJ-AS', 'project-as', 'Auto Select');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view']);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/construction/mobile/construction/context');

        $response->assertOk();
        $response->assertJsonPath('data.active_project.id', $project->id);
        $response->assertJsonPath('data.active_role.slug', 'surveyor');
    }

    public function test_context_multiple_projects_require_selection(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Project Member');
        $projectA = $this->createProject('PRJ-MPA', 'project-mpa', 'MPA');
        $projectB = $this->createProject('PRJ-MPB', 'project-mpb', 'MPB');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $projectA, ['survey.view']);
        $this->assignRoleToProject($member, $surveyor, $projectB, ['survey.view']);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/construction/mobile/construction/context');

        $response->assertOk();
        $response->assertJsonPath('data.active_project', null);
        $response->assertJsonPath('data.active_role', null);
        $response->assertJsonCount(2, 'data.projects');
    }

    public function test_context_valid_project_and_role(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Valid Context Member');
        $project = $this->createProject('PRJ-VC', 'project-vc', 'Valid Context');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignRoleToProject($member, $driver, $project, ['vehicle_tracking.manage']);

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=' . $project->id . '&role=vehicle_driver'
        );

        $response->assertOk();
        $response->assertJsonPath('data.active_project.id', $project->id);
        $response->assertJsonPath('data.active_role.slug', 'vehicle_driver');
        $this->assertContains('vehicle_tracking.manage', $response->json('data.permissions'));
    }

    public function test_context_wrong_project_role_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Wrong Role Member');
        $projectA = $this->createProject('PRJ-WRA', 'project-wra', 'Wrong Role A');
        $projectB = $this->createProject('PRJ-WRB', 'project-wrb', 'Wrong Role B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignRoleToProject($member, $surveyor, $projectA, ['survey.view']);
        $this->assignRoleToProject($member, $driver, $projectB, ['vehicle_tracking.manage']);

        // Request project A with the driver role (only valid on project B).
        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=' . $projectA->id . '&role=vehicle_driver'
        );

        $response->assertForbidden();
    }

    public function test_context_invalid_project_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Invalid Project Member');
        $project = $this->createProject('PRJ-IP', 'project-ip', 'Invalid Project');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view']);

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=999999'
        );

        $response->assertForbidden();
    }

    public function test_context_global_role_without_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Global Role Member');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignGlobalRole($member, $surveyor, ['survey.view']);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/construction/mobile/construction/context');

        $response->assertOk();
        $response->assertJsonPath('data.active_project', null);
        $response->assertJsonPath('data.active_role.slug', 'surveyor');
        $response->assertJsonCount(1, 'data.available_roles');
        $this->assertContains('survey.view', $response->json('data.permissions'));
    }

    public function test_context_mobile_surface_filters_permissions(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Surface Member');
        $project = $this->createProject('PRJ-SF', 'project-sf', 'Surface Filter');
        $reviewer = $this->createRole('review_approver', 'Review Approver');

        // drawing_approval.manage is web-only in the seeder; assign it as web surface.
        $this->assignRoleToProject($member, $reviewer, $project, ['drawing_approval.manage'], 'web');

        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=' . $project->id . '&role=review_approver'
        );

        $response->assertOk();
        $response->assertJsonPath('data.active_role.slug', 'review_approver');
        // Web-only permission must NOT appear in the mobile context.
        $this->assertNotContains('drawing_approval.manage', $response->json('data.permissions'));
    }

    public function test_context_role_specific_permissions_only(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Specific Member');
        $project = $this->createProject('PRJ-RS', 'project-rs', 'Role Specific');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view', 'survey.create']);
        $this->assignRoleToProject($member, $driver, $project, ['vehicle_tracking.manage']);

        // Active role = surveyor → only survey permissions, not driver permissions.
        $response = $this->actingAs($member, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=' . $project->id . '&role=surveyor'
        );

        $response->assertOk();
        $response->assertJsonPath('data.active_role.slug', 'surveyor');
        $this->assertContains('survey.view', $response->json('data.permissions'));
        $this->assertNotContains('vehicle_tracking.manage', $response->json('data.permissions'));
    }

    public function test_context_member_idor_protection(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Member A');
        $memberB = $this->createMember($admin, 'Member B');
        $projectA = $this->createProject('PRJ-ID1', 'project-id1', 'IDOR A');
        $projectB = $this->createProject('PRJ-ID2', 'project-id2', 'IDOR B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($memberA, $surveyor, $projectA, ['survey.view']);
        $this->assignRoleToProject($memberB, $surveyor, $projectB, ['survey.view']);

        // Member A tries to access Member B's project.
        $response = $this->actingAs($memberA, 'sanctum')->getJson(
            '/api/construction/mobile/construction/context?project=' . $projectB->id . '&role=surveyor'
        );

        $response->assertForbidden();
    }

    public function test_assigned_projects_uses_authorization_service(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Assigned Projects Member');
        $project = $this->createProject('PRJ-AP', 'project-ap', 'Assigned Projects');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignRoleToProject($member, $surveyor, $project, ['survey.view']);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/construction/mobile/construction/projects/assigned');

        $response->assertOk();
        $response->assertJsonCount(1, 'data');
        $response->assertJsonPath('data.0.id', $project->id);
    }
}