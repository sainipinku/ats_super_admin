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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectTeamAssignmentTest extends TestCase
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

    private function createRole(string $slug, string $name = 'Role', string $status = 'active'): Role
    {
        return Role::create([
            'name' => $name,
            'slug' => $slug,
            'description' => $name,
            'is_system_role' => true,
            'status' => $status,
        ]);
    }

    private function assignTeam(
        Project $project,
        Member $member,
        Role $role,
        string $status = 'active'
    ): ProjectTeamMember {
        $teamMember = ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $role->id,
            'status' => $status,
        ]);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $role->id,
            'project_id' => $project->id,
            'status' => $status,
        ]);

        return $teamMember;
    }

    /*
    |--------------------------------------------------------------------------
    | A. ASSIGNMENT
    |--------------------------------------------------------------------------
    */

    public function test_assign_member_with_first_role(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'First Role Member');
        $project = $this->createProject('PRJ-FR', 'project-fr', 'First Role');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $surveyor->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_project_team_members', [
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_assign_second_role_same_member_same_project(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Role Member');
        $project = $this->createProject('PRJ-MR', 'project-mr', 'Multi Role');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignTeam($project, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $driver->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_project_team_members', [
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
        // Surveyor assignment remains untouched.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_same_member_project_role_rejected(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Duplicate Member');
        $project = $this->createProject('PRJ-DU', 'project-du', 'Duplicate');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $this->assignTeam($project, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $surveyor->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseCount('construction_project_team_members', 1);
        $this->assertDatabaseCount('construction_member_role_assignments', 1);
    }

    public function test_member_can_have_roles_on_multiple_projects(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Multi Project Member');
        $projectA = $this->createProject('PRJ-MPA', 'project-mpa', 'Multi Project A');
        $projectB = $this->createProject('PRJ-MPB', 'project-mpb', 'Multi Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $this->assignTeam($projectA, $member, $surveyor);
        $this->assignTeam($projectB, $member, $driver);

        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectA->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $projectB->id,
            'status' => 'active',
        ]);
    }

    public function test_null_role_assignment_preserved(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Null Role Member');
        $project = $this->createProject('PRJ-NR', 'project-nr', 'Null Role');

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => null,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_project_team_members', [
            'project_id' => $project->id,
            'member_id' => $member->id,
            'role_id' => null,
            'status' => 'active',
        ]);
        // No MemberRoleAssignment should be created for null role.
        $this->assertDatabaseCount('construction_member_role_assignments', 0);
    }

    public function test_inactive_role_cannot_be_assigned(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Inactive Role Member');
        $project = $this->createProject('PRJ-IR', 'project-ir', 'Inactive Role');
        $inactiveRole = $this->createRole('inactive_role', 'Inactive Role', 'inactive');

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $inactiveRole->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseCount('construction_project_team_members', 0);
        $this->assertDatabaseCount('construction_member_role_assignments', 0);
    }

    /*
    |--------------------------------------------------------------------------
    | B. STATUS
    |--------------------------------------------------------------------------
    */

    public function test_deactivate_team_role_also_deactivates_member_role_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Deactivate Member');
        $project = $this->createProject('PRJ-DE', 'project-de', 'Deactivate');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamMember = $this->assignTeam($project, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->patch(
            route('super.construction.projects.team.status', [$project, $teamMember])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_project_team_members', [
            'id' => $teamMember->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
    }

    public function test_reactivate_team_role_also_reactivates_member_role_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Reactivate Member');
        $project = $this->createProject('PRJ-RE', 'project-re', 'Reactivate');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamMember = $this->assignTeam($project, $member, $surveyor, 'inactive');

        $response = $this->actingAs($admin, 'superadmin')->patch(
            route('super.construction.projects.team.status', [$project, $teamMember])
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('construction_project_team_members', [
            'id' => $teamMember->id,
            'status' => 'active',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_inactive_role_no_longer_grants_authorization(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'No Auth Member');
        $project = $this->createProject('PRJ-NA', 'project-na', 'No Auth');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamMember = $this->assignTeam($project, $member, $surveyor);

        // Deactivate.
        $this->actingAs($admin, 'superadmin')->patch(
            route('super.construction.projects.team.status', [$project, $teamMember])
        );

        // Authorization service must not grant the permission.
        $authorization = app(\App\Services\Construction\ConstructionAuthorizationService::class);
        $this->assertFalse($authorization->can($member, 'survey.view', $project, 'mobile'));
    }

    public function test_unrelated_roles_remain_active_when_one_deactivated(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Unrelated Roles Member');
        $project = $this->createProject('PRJ-UR', 'project-ur', 'Unrelated Roles');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $surveyorTeam = $this->assignTeam($project, $member, $surveyor);
        $this->assignTeam($project, $member, $driver);

        // Deactivate only the surveyor assignment.
        $this->actingAs($admin, 'superadmin')->patch(
            route('super.construction.projects.team.status', [$project, $surveyorTeam])
        );

        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | C. UPDATE
    |--------------------------------------------------------------------------
    */

    public function test_change_role_surveyor_to_driver_synchronizes_authorization(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Role Change Member');
        $project = $this->createProject('PRJ-RC', 'project-rc', 'Role Change');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $teamMember = $this->assignTeam($project, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$project, $teamMember]),
            [
                'member_id' => $member->id,
                'role_id' => $driver->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        // Old surveyor authorization deactivated.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
        // New driver authorization active.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_old_role_does_not_remain_active_after_change(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Stale Role Member');
        $project = $this->createProject('PRJ-ST', 'project-st', 'Stale Role');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $teamMember = $this->assignTeam($project, $member, $surveyor);

        $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$project, $teamMember]),
            [
                'member_id' => $member->id,
                'role_id' => $driver->id,
                'status' => 'active',
            ]
        );

        // Surveyor must NOT remain active.
        $this->assertDatabaseMissing('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_changing_one_role_does_not_deactivate_other_roles(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Isolated Change Member');
        $project = $this->createProject('PRJ-IC', 'project-ic', 'Isolated Change');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');
        $siteEmployee = $this->createRole('site_employee', 'Site Employee');

        $surveyorTeam = $this->assignTeam($project, $member, $surveyor);
        $this->assignTeam($project, $member, $driver);

        // Change surveyor → site_employee.
        $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$project, $surveyorTeam]),
            [
                'member_id' => $member->id,
                'role_id' => $siteEmployee->id,
                'status' => 'active',
            ]
        );

        // Driver remains active.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
        // Surveyor deactivated.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
        // Site employee active.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $siteEmployee->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_change_member_synchronizes_old_and_new_authorization(): void
    {
        $admin = $this->createSuperAdmin();
        $memberA = $this->createMember($admin, 'Old Member');
        $memberB = $this->createMember($admin, 'New Member');
        $project = $this->createProject('PRJ-CM', 'project-cm', 'Change Member');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamMember = $this->assignTeam($project, $memberA, $surveyor);

        $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$project, $teamMember]),
            [
                'member_id' => $memberB->id,
                'role_id' => $surveyor->id,
                'status' => 'active',
            ]
        );

        // Old member's authorization deactivated.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $memberA->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
        // New member's authorization active.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $memberB->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_duplicate_target_role_rejected_on_update(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Duplicate Update Member');
        $project = $this->createProject('PRJ-DU2', 'project-du2', 'Duplicate Update');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $surveyorTeam = $this->assignTeam($project, $member, $surveyor);
        $this->assignTeam($project, $member, $driver);

        // Try to change surveyor team row to driver (already assigned).
        $response = $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$project, $surveyorTeam]),
            [
                'member_id' => $member->id,
                'role_id' => $driver->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('role_id');
    }

    /*
    |--------------------------------------------------------------------------
    | D. DELETE
    |--------------------------------------------------------------------------
    */

    public function test_delete_team_role_deactivates_member_role_assignment(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Delete Member');
        $project = $this->createProject('PRJ-DL', 'project-dl', 'Delete');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamMember = $this->assignTeam($project, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->delete(
            route('super.construction.projects.team.destroy', [$project, $teamMember])
        );

        $response->assertRedirect();
        $this->assertDatabaseMissing('construction_project_team_members', ['id' => $teamMember->id]);
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $project->id,
            'status' => 'inactive',
        ]);
    }

    public function test_delete_one_role_does_not_affect_other_roles(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Isolated Delete Member');
        $project = $this->createProject('PRJ-ID', 'project-id', 'Isolated Delete');
        $surveyor = $this->createRole('surveyor', 'Surveyor');
        $driver = $this->createRole('vehicle_driver', 'Vehicle Driver');

        $surveyorTeam = $this->assignTeam($project, $member, $surveyor);
        $this->assignTeam($project, $member, $driver);

        $this->actingAs($admin, 'superadmin')->delete(
            route('super.construction.projects.team.destroy', [$project, $surveyorTeam])
        );

        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $driver->id,
            'project_id' => $project->id,
            'status' => 'active',
        ]);
    }

    public function test_delete_assignment_from_project_a_does_not_affect_project_b(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Cross Project Delete Member');
        $projectA = $this->createProject('PRJ-CP1', 'project-cp1', 'Cross Project A');
        $projectB = $this->createProject('PRJ-CP2', 'project-cp2', 'Cross Project B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamA = $this->assignTeam($projectA, $member, $surveyor);
        $this->assignTeam($projectB, $member, $surveyor);

        $this->actingAs($admin, 'superadmin')->delete(
            route('super.construction.projects.team.destroy', [$projectA, $teamA])
        );

        // Project B assignment remains active.
        $this->assertDatabaseHas('construction_member_role_assignments', [
            'member_id' => $member->id,
            'role_id' => $surveyor->id,
            'project_id' => $projectB->id,
            'status' => 'active',
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | E. SECURITY / IDOR
    |--------------------------------------------------------------------------
    */

    public function test_assignment_from_another_project_cannot_be_modified(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'IDOR Member');
        $projectA = $this->createProject('PRJ-ID1', 'project-id1', 'IDOR A');
        $projectB = $this->createProject('PRJ-ID2', 'project-id2', 'IDOR B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamA = $this->assignTeam($projectA, $member, $surveyor);

        // Try to update project A's assignment via project B route.
        $response = $this->actingAs($admin, 'superadmin')->put(
            route('super.construction.projects.team.update', [$projectB, $teamA]),
            [
                'member_id' => $member->id,
                'role_id' => $surveyor->id,
                'status' => 'active',
            ]
        );

        $response->assertNotFound();
    }

    public function test_assignment_id_from_another_project_cannot_be_toggled(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'IDOR Toggle Member');
        $projectA = $this->createProject('PRJ-IT1', 'project-it1', 'IDOR Toggle A');
        $projectB = $this->createProject('PRJ-IT2', 'project-it2', 'IDOR Toggle B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamA = $this->assignTeam($projectA, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->patch(
            route('super.construction.projects.team.status', [$projectB, $teamA])
        );

        $response->assertNotFound();
    }

    public function test_assignment_id_from_another_project_cannot_be_deleted(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'IDOR Delete Member');
        $projectA = $this->createProject('PRJ-IDD1', 'project-idd1', 'IDOR Delete A');
        $projectB = $this->createProject('PRJ-IDD2', 'project-idd2', 'IDOR Delete B');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        $teamA = $this->assignTeam($projectA, $member, $surveyor);

        $response = $this->actingAs($admin, 'superadmin')->delete(
            route('super.construction.projects.team.destroy', [$projectB, $teamA])
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('construction_project_team_members', ['id' => $teamA->id]);
    }

    public function test_unauthorized_actor_cannot_modify_assignments(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Unauthorized Member');
        $project = $this->createProject('PRJ-UA', 'project-ua', 'Unauthorized');
        $surveyor = $this->createRole('surveyor', 'Surveyor');

        // A regular member (not superadmin) tries to assign a team role.
        // The auth.superadmin middleware redirects unauthorized actors to login (302).
        $response = $this->actingAs($member, 'member')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $surveyor->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $this->assertDatabaseCount('construction_project_team_members', 0);
    }

    public function test_deleted_role_cannot_be_assigned(): void
    {
        $admin = $this->createSuperAdmin();
        $member = $this->createMember($admin, 'Deleted Role Member');
        $project = $this->createProject('PRJ-DR', 'project-dr', 'Deleted Role');
        $role = $this->createRole('deleted_role', 'Deleted Role');
        $role->delete();

        $response = $this->actingAs($admin, 'superadmin')->post(
            route('super.construction.projects.team.assign', $project),
            [
                'member_id' => $member->id,
                'role_id' => $role->id,
                'status' => 'active',
            ]
        );

        $response->assertRedirect();
        $response->assertSessionHasErrors('role_id');
        $this->assertDatabaseCount('construction_project_team_members', 0);
    }
}