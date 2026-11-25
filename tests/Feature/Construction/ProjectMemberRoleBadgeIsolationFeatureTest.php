<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectMemberRoleBadgeIsolationFeatureTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;
    private SuperAdmin $companySuperAdmin;
    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed exact colliding Role IDs (1..9) vs ConstructionRole IDs (1..6) from production schema
        foreach ([
            ['id' => 1, 'name' => 'Member', 'slug' => 'member'],
            ['id' => 2, 'name' => 'Super Admin', 'slug' => 'super_admin'],
            ['id' => 3, 'name' => 'Project Admin', 'slug' => 'project_admin'],
            ['id' => 4, 'name' => 'Surveyor', 'slug' => 'surveyor'],
            ['id' => 5, 'name' => 'Draft Man', 'slug' => 'draft_person'],
            ['id' => 6, 'name' => 'Vehicle Driver', 'slug' => 'vehicle_driver'],
            ['id' => 7, 'name' => 'Review Approver', 'slug' => 'review_approver'],
            ['id' => 8, 'name' => 'Site Employee', 'slug' => 'site_employee'],
            ['id' => 9, 'name' => 'Admin', 'slug' => 'admin'],
        ] as $rData) {
            $r = new Role();
            $r->forceFill([
                'id' => $rData['id'],
                'name' => $rData['name'],
                'slug' => $rData['slug'],
                'status' => 1,
                'is_default' => 1,
                'created_by' => 1,
            ])->save();
        }

        foreach ([
            ['id' => 1, 'name' => 'Project Admin', 'slug' => 'project_admin'],
            ['id' => 2, 'name' => 'Surveyor', 'slug' => 'surveyor'],
            ['id' => 3, 'name' => 'Draft Person', 'slug' => 'draft_person'],
            ['id' => 4, 'name' => 'Vehicle Driver', 'slug' => 'vehicle_driver'],
            ['id' => 5, 'name' => 'Review Approver', 'slug' => 'review_approver'],
            ['id' => 6, 'name' => 'Site Employee', 'slug' => 'site_employee'],
        ] as $cData) {
            $cr = new ConstructionRole();
            $cr->forceFill([
                'id' => $cData['id'],
                'company_id' => null,
                'name' => $cData['name'],
                'slug' => $cData['slug'],
                'is_system_role' => true,
                'status' => 'active',
            ])->save();
        }

        $this->company = Company::create([
            'name' => 'GoRun Survey Infra',
            'status' => 'active',
        ]);

        $this->companySuperAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'GoRun',
            'email' => 'gorun@gmail.com',
            'phone' => '9876500001',
            'whatsapp_phone' => '9876500001',
            'password' => Hash::make('password123'),
            'status' => 1,
            'company_id' => $this->company->id,
            'roles' => ['super_admin'],
        ]);

        $client = Client::create([
            'company_id' => $this->company->id,
            'name' => 'Vikas Saini',
            'client_type' => 'individual',
            'status' => 'active',
        ]);

        $this->project = Project::create([
            'company_id' => $this->company->id,
            'client_id' => $client->id,
            'name' => 'Chomu Ploat Side Map Survey',
            'status' => 'active',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $this->companySuperAdmin->id,
        ]);
    }

    public function test_newly_added_surveyor_shows_only_surveyor_and_never_collides_with_construction_role_id_4_vehicle_driver(): void
    {
        $storeResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->post(route('super.employees.store'), [
                'full_name' => 'priynaka saini',
                'email' => 'priynkasaini@gmail.com',
                'phone' => '9876543219',
                'password' => 'password123',
                'confirm_password' => 'password123',
                'company_id' => $this->company->id,
                'role' => 'member',
                'roles' => ['surveyor'],
                'department' => 'Survey',
                'designation' => 'Surveyor',
                'status' => 1,
            ]);

        $storeResp->assertRedirect();
        $storeResp->assertSessionHasNoErrors();

        $priyanka = Member::where('email', 'priynkasaini@gmail.com')->firstOrFail();

        // Should NOT leave unselected site_employee rows cluttering assignment history
        $this->assertSame(1, MemberRoleAssignment::where('member_id', $priyanka->id)->count());
        $this->assertFalse($priyanka->isDriver());

        // Also create a Draft Person (Role id=5 vs ConstructionRole id=5 Review Approver)
        $draftsman = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Abhishek Tiwari',
            'username' => 'abhishek_t',
            'slug' => 'abhishek-t',
            'email' => 'abhishektiwari1540@gmail.com',
            'phone' => '9876543220',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Drafting'],
            'designation' => ['Draftsman'],
            'roles' => [5],
            'company_name' => $this->company->name,
            'created_by' => $this->companySuperAdmin->id,
            'password' => Hash::make('password123'),
        ]);
        MemberRoleAssignment::create(['member_id' => $draftsman->id, 'role_id' => 3, 'status' => 1]);

        $showResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->project->id));

        $showResp->assertOk();
        $members = collect($showResp->viewData('page')['props']['members']);

        $priyankaRow = $members->firstWhere('email', 'priynkasaini@gmail.com');
        $this->assertNotNull($priyankaRow);
        $this->assertSame(['Surveyor'], $priyankaRow['role_badges']);
        $this->assertSame('[Surveyor]', $priyankaRow['roles_formatted']);
        $this->assertNotContains('Vehicle Driver', $priyankaRow['role_badges']);
        $this->assertNotContains('Driver', $priyankaRow['role_badges']);

        // SuperAdmin member (Role id=2) must NOT get Surveyor (ConstructionRole id=2)
        $superAdminRow = $members->firstWhere('email', 'gorun@gmail.com');
        $this->assertNotNull($superAdminRow);
        $this->assertContains('Super Admin', $superAdminRow['role_badges']);
        $this->assertNotContains('Surveyor', $superAdminRow['role_badges']);

        // Draftsman (Role id=5 Draft Man) must NOT get Review Approver (ConstructionRole id=5)
        $draftsmanRow = $members->firstWhere('email', 'abhishektiwari1540@gmail.com');
        $this->assertNotNull($draftsmanRow);
        $this->assertSame(['Draft Person'], $draftsmanRow['role_badges']);
        $this->assertNotContains('Review Approver', $draftsmanRow['role_badges']);
    }

    public function test_stale_raw_driver_role_id_is_ignored_in_project_dropdown_when_member_only_has_active_surveyor_assignment(): void
    {
        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Pinku Scoped',
            'username' => 'pinku_scoped',
            'slug' => 'pinku-scoped',
            'email' => 'pinku_scoped@gmail.com',
            'phone' => '9876543221',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Survey'],
            'designation' => ['Surveyor'],
            'roles' => [4, 6], // stale driver ID 6 in raw column
            'company_name' => $this->company->name,
            'created_by' => $this->companySuperAdmin->id,
            'password' => Hash::make('password123'),
        ]);

        // Active Surveyor assignment, deactivated Driver assignment
        MemberRoleAssignment::create(['member_id' => $member->id, 'role_id' => 2, 'status' => 1]);
        MemberRoleAssignment::create(['member_id' => $member->id, 'role_id' => 4, 'status' => 0]);

        $showResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->project->id));

        $showResp->assertOk();
        $members = collect($showResp->viewData('page')['props']['members']);
        $pinkuRow = $members->firstWhere('email', 'pinku_scoped@gmail.com');

        $this->assertNotNull($pinkuRow);
        $this->assertSame(['Surveyor'], $pinkuRow['role_badges']);
        $this->assertNotContains('Vehicle Driver', $pinkuRow['role_badges']);
    }

    public function test_admin_draftsman_candidates_does_not_treat_project_admin_role_id_3_as_draft_person_construction_role_id_3(): void
    {
        $adminMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Project Admin User',
            'username' => 'proj_admin_u',
            'slug' => 'proj-admin-u',
            'email' => 'projadmin@example.com',
            'phone' => '9876543222',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Management'],
            'designation' => ['Manager'],
            'roles' => [3], // Role ID 3 = Project Admin (NOT ConstructionRole ID 3 Draft Person!)
            'company_name' => $this->company->name,
            'created_by' => $this->companySuperAdmin->id,
            'password' => Hash::make('password123'),
        ]);

        ProjectTeamMember::create([
            'project_id' => $this->project->id,
            'member_id' => $adminMember->id,
            'role_id' => 1, // ConstructionRole 1 = Project Admin
            'status' => 'active',
        ]);

        $resp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('admin.construction.projects.show', $this->project->id));

        $resp->assertOk();
        $draftsmen = collect($resp->viewData('page')['props']['draftsmen'] ?? []);
        $this->assertFalse($draftsmen->contains('id', $adminMember->id));
    }

    public function test_project_show_roles_prop_uses_construction_role_ids_so_assigning_surveyor_never_saves_vehicle_driver(): void
    {
        // Custom role created in Role Management (roles table) should also sync dynamically into construction roles
        Role::create([
            'name' => 'Draft Person 22',
            'slug' => 'draft_person_22',
            'status' => 1,
            'is_default' => 0,
            'created_by' => $this->companySuperAdmin->id,
            'super_admin_id' => $this->companySuperAdmin->id,
        ]);

        // Add employee with ONLY Surveyor
        $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->post(route('super.employees.store'), [
                'full_name' => 'gfdgfdgdfg',
                'email' => 'fdgdfgdfg@gmail.com',
                'phone' => '9680348275',
                'password' => 'password123',
                'confirm_password' => 'password123',
                'company_id' => $this->company->id,
                'role' => 'member',
                'roles' => ['surveyor'],
                'department' => 'Survey',
                'designation' => 'Surveyor',
                'status' => 1,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $surveyMember = Member::where('email', 'fdgdfgdfg@gmail.com')->firstOrFail();

        $showResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->project->id));
        $showResp->assertOk();

        $roles = collect($showResp->viewData('page')['props']['roles']);
        $surveyorOption = $roles->firstWhere('slug', 'surveyor');
        $customRoleOption = $roles->firstWhere('slug', 'draft_person_22');

        $this->assertNotNull($surveyorOption);
        $this->assertNotNull($customRoleOption, 'Custom company roles must dynamically appear in Construction Role dropdown');

        // Critically: role_id returned to the UI must be a ConstructionRole (slug=surveyor), NOT Role id=4 (which in construction_roles is Vehicle Driver!)
        $resolvedConstructionRole = ConstructionRole::find($surveyorOption['id']);
        $this->assertNotNull($resolvedConstructionRole);
        $this->assertSame('surveyor', $resolvedConstructionRole->slug);
        $this->assertNotSame(4, (int) $surveyorOption['id'], 'Surveyor option ID must never collide with ConstructionRole ID 4 (Vehicle Driver)');

        // Assigning Team Member using the exact option returned by Show must persist Surveyor, NEVER Vehicle Driver
        $assignResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->post(route('super.construction.projects.team.assign', $this->project->id), [
                'member_id' => $surveyMember->id,
                'role_id' => $surveyorOption['id'],
                'assignment_scope' => 'Field Survey & Site Data Collection',
                'is_primary' => true,
            ]);

        $assignResp->assertRedirect()->assertSessionHasNoErrors();

        $teamMember = ProjectTeamMember::where('project_id', $this->project->id)
            ->where('member_id', $surveyMember->id)
            ->with('role')
            ->firstOrFail();

        $this->assertSame('surveyor', $teamMember->role?->slug);
        $this->assertNotSame('Vehicle Driver', $teamMember->role?->name);

        // Reload Project Show and assert dropdown badge shows ONLY the member's dynamic role and never Vehicle Driver
        $afterResp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->project->id));
        $afterResp->assertOk();

        $members = collect($afterResp->viewData('page')['props']['members']);
        $memberRow = $members->firstWhere('id', $surveyMember->id);

        $this->assertNotNull($memberRow);
        $this->assertContains('Surveyor', $memberRow['role_badges']);
        $this->assertNotContains('Vehicle Driver', $memberRow['role_badges']);
        $this->assertNotContains('Driver', $memberRow['role_badges']);
    }

    public function test_get_project_members_uses_dynamic_database_role_names_instead_of_static_overrides(): void
    {
        $customNamedSurveyor = ConstructionRole::create([
            'company_id' => $this->company->id,
            'name' => 'Lead Survey Officer',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Dynamic Badge User',
            'username' => 'dyn_badge_u',
            'slug' => 'dyn-badge-u',
            'email' => 'dynbadge@example.com',
            'phone' => '9876543288',
            'status' => Member::STATUS_ACTIVE,
            'departments' => ['Survey'],
            'designation' => ['Surveyor'],
            'roles' => [],
            'company_name' => $this->company->name,
            'created_by' => $this->companySuperAdmin->id,
            'password' => Hash::make('password123'),
        ]);

        MemberRoleAssignment::create([
            'member_id' => $member->id,
            'role_id' => $customNamedSurveyor->id,
            'status' => 1,
        ]);

        $resp = $this->actingAs($this->companySuperAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $this->project->id));
        $resp->assertOk();

        $members = collect($resp->viewData('page')['props']['members']);
        $row = $members->firstWhere('id', $member->id);

        $this->assertNotNull($row);
        $this->assertSame(['Lead Survey Officer'], $row['role_badges']);
    }
}
