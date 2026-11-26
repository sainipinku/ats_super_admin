<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class EmployeeTeamProjectScopingFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private function actingOnlyAs($user, string $guard)
    {
        foreach (['superadmin', 'admin', 'member', 'web'] as $g) {
            if (auth()->guard($g)->check()) {
                auth()->guard($g)->logout();
            }
        }
        session()->flush();

        return $this->actingAs($user, $guard);
    }

    private function createScopingFixture(): array
    {
        $suffix = Str::lower(Str::random(5));

        $company = Company::create([
            'name' => "Team Scope Co {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "SuperAdmin {$suffix}",
            'email' => "sa-scope-{$suffix}@example.com",
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $company->update([
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $p1 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-1-' . Str::upper($suffix),
            'name' => "Assigned Project {$suffix}",
            'slug' => "assigned-project-{$suffix}",
            'status' => 'active',
            'current_stage' => 'drafting_in_progress',
        ]);

        $p2 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-2-' . Str::upper($suffix),
            'name' => "Unassigned Project A {$suffix}",
            'slug' => "unassigned-a-{$suffix}",
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $p3 = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-3-' . Str::upper($suffix),
            'name' => "Inactive Team Project {$suffix}",
            'slug' => "inactive-team-{$suffix}",
            'status' => 'active',
            'current_stage' => 'ready_for_construction',
        ]);

        $permView = Permission::firstOrCreate(['slug' => 'project.view'], ['name' => 'View Projects', 'module' => 'Project Lifecycle']);
        $permManage = Permission::firstOrCreate(['slug' => 'project.manage'], ['name' => 'Manage Projects', 'module' => 'Project Lifecycle']);
        $permDash = Permission::firstOrCreate(['slug' => 'dashboard.view'], ['name' => 'View Dashboard', 'module' => 'Overview']);

        $sysMemberRole = Role::firstOrCreate(['slug' => 'member'], ['name' => 'Member', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $sysAdminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);

        $cDraftRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Draft Person',
            'slug' => 'draft_person',
            'status' => 'active',
        ]);
        $cSurveyorRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);
        $cAdminRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Admin',
            'slug' => 'admin',
            'status' => 'active',
        ]);
        $cProjectAdminRole = ConstructionRole::create([
            'company_id' => $company->id,
            'name' => 'Project Admin',
            'slug' => 'project_admin',
            'status' => 'active',
        ]);

        foreach ([$cDraftRole, $cSurveyorRole, $cAdminRole, $cProjectAdminRole] as $cRole) {
            DB::table('construction_role_permissions')->insertOrIgnore([
                ['role_id' => $cRole->id, 'permission_id' => $permView->id],
                ['role_id' => $cRole->id, 'permission_id' => $permDash->id],
            ]);
        }
        DB::table('construction_role_permissions')->insertOrIgnore([
            ['role_id' => $cAdminRole->id, 'permission_id' => $permManage->id],
            ['role_id' => $cProjectAdminRole->id, 'permission_id' => $permManage->id],
        ]);

        $makeMember = function (string $prefix, int $sysRoleId, ConstructionRole $cRole, ?int $projectId = null) use ($company, $superAdmin, $suffix): Member {
            $token = Str::lower(Str::random(5));
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'created_by' => $superAdmin->id,
                'company_name' => $company->name,
                'name' => "{$prefix} {$token}",
                'username' => "{$prefix}_{$token}_{$suffix}",
                'slug' => Str::slug("{$prefix}-{$token}-{$suffix}"),
                'email' => Str::slug("{$prefix}-{$token}-{$suffix}") . '@example.com',
                'phone' => (string) random_int(7000000000, 9999999999),
                'password' => Hash::make('password123'),
                'status' => 1,
                'roles' => [$sysRoleId],
            ]);

            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $cRole->id,
                'project_id' => $projectId,
                'status' => 1,
            ]);

            return $member;
        };

        // Draftsman added ONLY to P1 (active), inactive on P3, NOT on P2
        $draftsman = $makeMember('draftsman', $sysMemberRole->id, $cDraftRole);
        ProjectTeamMember::create([
            'project_id' => $p1->id,
            'member_id' => $draftsman->id,
            'role_id' => $cDraftRole->id,
            'status' => 'active',
        ]);
        ProjectTeamMember::create([
            'project_id' => $p3->id,
            'member_id' => $draftsman->id,
            'role_id' => $cDraftRole->id,
            'status' => 'inactive',
        ]);

        // Unassigned Surveyor (not in any project's team)
        $unassignedEmployee = $makeMember('surveyor_free', $sysMemberRole->id, $cSurveyorRole);

        // Company-wide Admin (NOT a ProjectTeamMember of any project)
        $companyAdmin = $makeMember('company_admin', $sysAdminRole->id, $cAdminRole);

        // Project-scoped Project Admin ONLY on P1 (not company-wide admin)
        $projectScopedAdmin = $makeMember('proj_admin', $sysMemberRole->id, $cProjectAdminRole, $p1->id);

        return compact(
            'company',
            'superAdmin',
            'p1',
            'p2',
            'p3',
            'draftsman',
            'unassignedEmployee',
            'companyAdmin',
            'projectScopedAdmin'
        );
    }

    public function test_employee_logged_in_via_admin_guard_sees_only_active_team_projects_and_blocks_others(): void
    {
        $f = $this->createScopingFixture();

        // Real ERP login authenticates Members on 'admin' guard
        $superProjects = $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('super.construction.projects.index'));
        $superProjects->assertOk();
        $superProjects->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $f['p1']->id)
        );

        $adminProjects = $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('admin.construction.projects.index'));
        $adminProjects->assertOk();
        $adminProjects->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $f['p1']->id)
        );

        $superDash = $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('super.construction.dashboard'));
        $superDash->assertOk();
        $superDash->assertInertia(fn ($page) => $page
            ->where('stats.projects.total', 1)
            ->has('recentProjects', 1)
            ->where('recentProjects.0.id', $f['p1']->id)
        );

        $adminDash = $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('admin.construction.dashboard'));
        $adminDash->assertOk();
        $adminDash->assertInertia(fn ($page) => $page
            ->where('stats.projects.total', 1)
            ->has('recentProjects', 1)
            ->where('recentProjects.0.id', $f['p1']->id)
        );

        // Direct URL access to unassigned P2 or inactive-team P3 must be 403
        $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('super.construction.projects.show', $f['p2']->id))
            ->assertStatus(403);

        $this->actingOnlyAs($f['draftsman'], 'admin')
            ->get(route('super.construction.projects.show', $f['p3']->id))
            ->assertStatus(403);

        // Employee not in any project team sees zero projects everywhere
        $freeRes = $this->actingOnlyAs($f['unassignedEmployee'], 'admin')
            ->get(route('super.construction.projects.index'));
        $freeRes->assertOk();
        $freeRes->assertInertia(fn ($page) => $page->has('projects', 0));

        // Project-scoped admin (only assigned to P1) sees only P1, not all 3
        $scopedRes = $this->actingOnlyAs($f['projectScopedAdmin'], 'admin')
            ->get(route('super.construction.projects.index'));
        $scopedRes->assertOk();
        $scopedRes->assertInertia(fn ($page) => $page
            ->has('projects', 1)
            ->where('projects.0.id', $f['p1']->id)
        );
    }

    public function test_company_admin_and_super_admin_see_all_company_projects_unless_switched_to_worker_role(): void
    {
        $f = $this->createScopingFixture();

        // Company Super Admin sees all 3 projects
        $saRes = $this->actingOnlyAs($f['superAdmin'], 'superadmin')
            ->get(route('super.construction.projects.index'));
        $saRes->assertOk();
        $saRes->assertInertia(fn ($page) => $page->has('projects', 3));

        // Company-wide Admin sees all 3 projects even without team membership
        $adminRes = $this->actingOnlyAs($f['companyAdmin'], 'admin')
            ->get(route('super.construction.projects.index'));
        $adminRes->assertOk();
        $adminRes->assertInertia(fn ($page) => $page->has('projects', 3));

        $adminPanelRes = $this->actingOnlyAs($f['companyAdmin'], 'admin')
            ->get(route('admin.construction.projects.index'));
        $adminPanelRes->assertOk();
        $adminPanelRes->assertInertia(fn ($page) => $page->has('projects', 3));

        // If Super Admin also carries a worker role and switches active_role to it with no team assignment, sees 0
        $f['superAdmin']->update(['roles' => ['super_admin', 'surveyor']]);
        $switchedSaRes = $this->actingOnlyAs($f['superAdmin'], 'superadmin')
            ->withSession(['active_role' => 'surveyor'])
            ->get(route('super.construction.projects.index'));
        $switchedSaRes->assertOk();
        $switchedSaRes->assertInertia(fn ($page) => $page->has('projects', 0));
    }
}
