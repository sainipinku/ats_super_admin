<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\DraftingJob;
use App\Models\DrawingApproval;
use App\Models\DrawingRevision;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\SurveyPlan;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Services\Construction\CompanyRoleProvisioningService;
use Database\Seeders\Construction\FoundationSeeder;
use Database\Seeders\DrawingApprovalAssignPermissionSeeder;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class CadApprovalAdminAssignmentFeatureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

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

    private function seedCorePermissionsAndRoles(): void
    {
        $this->seed(PermissionSeeder::class);
        $this->seed(SuperAdminPermissionSeeder::class);
        $this->seed(FoundationSeeder::class);
        $this->seed(DrawingApprovalAssignPermissionSeeder::class);
    }

    private function ensureLegacyRoles(): array
    {
        $adminRole = Role::firstOrCreate(['slug' => 'admin'], ['name' => 'Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $projectAdminRole = Role::firstOrCreate(['slug' => 'project_admin'], ['name' => 'Project Admin', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);
        $draftRole = Role::firstOrCreate(['slug' => 'draft_person'], ['name' => 'Draft Person', 'status' => 1, 'is_default' => 1, 'created_by' => 1]);

        return [$adminRole, $projectAdminRole, $draftRole];
    }

    private function createCompanyFixture(string $suffix): array
    {
        $company = Company::create([
            'name' => "CAD Approval Co {$suffix}",
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => ['super_admin'],
            'name' => "SuperAdmin {$suffix}",
            'email' => "sa-{$suffix}-" . Str::lower(Str::random(5)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $company->update([
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        app(CompanyRoleProvisioningService::class)->provisionCompanyRoles($company->id);
        app(CompanyRoleProvisioningService::class)->syncAllSuperAdminAndAdminPermissions();

        [$adminRole, $projectAdminRole, $draftRole] = $this->ensureLegacyRoles();

        $cProjectAdminRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'project_admin')->firstOrFail();
        $cAdminRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'admin')->firstOrFail();
        $cDraftRole = ConstructionRole::where('company_id', $company->id)->where('slug', 'draft_person')->firstOrFail();

        $makeMember = function (string $label, Role $sysRole, ConstructionRole $cRole, ?int $projectId = null) use ($company, $superAdmin): Member {
            $token = Str::lower(Str::random(6));
            $member = Member::create([
                'uuid' => (string) Str::uuid(),
                'created_by' => $superAdmin->id,
                'company_name' => $company->name,
                'name' => "{$label} {$token}",
                'username' => "{$label}_{$token}",
                'slug' => Str::slug("{$label}-{$token}"),
                'email' => Str::slug("{$label}-{$token}") . '@example.com',
                'phone' => (string) random_int(7000000000, 9999999999),
                'password' => Hash::make('password123'),
                'status' => 1,
                'roles' => [$sysRole->id],
            ]);

            MemberRoleAssignment::create([
                'member_id' => $member->id,
                'role_id' => $cRole->id,
                'project_id' => $projectId,
                'status' => 1,
            ]);

            return $member;
        };

        $adminOne = $makeMember('admin_one', $projectAdminRole, $cProjectAdminRole);
        $adminTwo = $makeMember('admin_two', $adminRole, $cAdminRole);
        $draftsman = $makeMember('draftsman', $draftRole, $cDraftRole);

        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => "Client {$suffix}",
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => "Project {$suffix}",
            'slug' => 'proj-' . Str::lower(Str::random(6)),
            'status' => 'active',
            'current_stage' => 'drafting_in_progress',
            'created_by_type' => SuperAdmin::class,
            'created_by_id' => $superAdmin->id,
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $draftsman->id,
            'role_id' => $cDraftRole->id,
            'status' => 'active',
        ]);

        $plan = SurveyPlan::create([
            'project_id' => $project->id,
            'survey_code' => 'SP-' . Str::upper(Str::random(6)),
            'title' => 'Survey Plan',
            'status' => 1,
        ]);

        $visit = SurveyVisit::create([
            'project_id' => $project->id,
            'survey_plan_id' => $plan->id,
            'member_id' => $draftsman->id,
            'checked_in_by_member_id' => $draftsman->id,
            'status' => 2,
        ]);

        $submission = SurveySubmission::create([
            'project_id' => $project->id,
            'survey_visit_id' => $visit->id,
            'submitted_by_member_id' => $draftsman->id,
            'status' => SurveySubmission::STATUS_APPROVED,
            'submitted_at' => now(),
        ]);

        $draftingJob = DraftingJob::create([
            'project_id' => $project->id,
            'survey_submission_id' => $submission->id,
            'assigned_to_member_id' => $draftsman->id,
            'status' => 'queued',
        ]);

        return [
            'company' => $company,
            'superAdmin' => $superAdmin,
            'adminOne' => $adminOne,
            'adminTwo' => $adminTwo,
            'draftsman' => $draftsman,
            'project' => $project,
            'draftingJob' => $draftingJob,
            'cAdminRole' => $cAdminRole,
            'cProjectAdminRole' => $cProjectAdminRole,
        ];
    }

    public function test_seeder_grants_assign_admin_permission_to_all_company_admins_and_super_admins(): void
    {
        $this->seedCorePermissionsAndRoles();
        $fixtureA = $this->createCompanyFixture('seedA');

        $permission = Permission::where('slug', 'drawing_approval.assign_admin')->first();
        $this->assertNotNull($permission, 'drawing_approval.assign_admin permission must exist after seeding');

        // Re-run incremental seeder to simulate deploy seeder execution on existing companies
        $this->seed(DrawingApprovalAssignPermissionSeeder::class);

        foreach (['super_admin', 'admin', 'project_admin'] as $roleSlug) {
            $companyRole = ConstructionRole::where('company_id', $fixtureA['company']->id)
                ->where('slug', $roleSlug)
                ->first();
            $this->assertNotNull($companyRole, "Company role {$roleSlug} must exist");
            $this->assertDatabaseHas('construction_role_permissions', [
                'role_id' => $companyRole->id,
                'permission_id' => $permission->id,
            ]);
        }

        $draftRole = ConstructionRole::where('company_id', $fixtureA['company']->id)
            ->where('slug', 'draft_person')
            ->first();
        $this->assertDatabaseMissing('construction_role_permissions', [
            'role_id' => $draftRole->id,
            'permission_id' => $permission->id,
        ]);
    }

    public function test_cad_submission_does_not_auto_assign_admin_and_only_company_admins_and_super_admins_can_assign(): void
    {
        $this->seedCorePermissionsAndRoles();
        $fixtureA = $this->createCompanyFixture('A');
        $fixtureB = $this->createCompanyFixture('B');

        // 1. Draft person submits CAD drawing -> approval stays pending and UNASSIGNED (no auto-assign)
        $submitRes = $this->actingOnlyAs($fixtureA['draftsman'], 'admin')
            ->from(route('super.construction.projects.show', $fixtureA['project']->id))
            ->post(route('super.construction.projects.tasks-v2.submit-draft', $fixtureA['project']->id), [
                'drafting_job_id' => $fixtureA['draftingJob']->id,
                'drawing_type' => 'topographic',
                'drawing_no' => 'DWG-101',
                'pdf_file' => UploadedFile::fake()->create('plan.pdf', 120, 'application/pdf'),
            ]);

        $submitRes->assertStatus(302);
        $submitRes->assertSessionHasNoErrors();

        $approval = DrawingApproval::where('project_id', $fixtureA['project']->id)->latest('id')->first();
        $this->assertNotNull($approval);
        $this->assertSame('pending', $approval->decision);
        $this->assertNull($approval->assigned_to_type, 'CAD approval must NOT auto-assign an admin on submission');
        $this->assertNull($approval->assigned_to_id, 'CAD approval must NOT auto-assign an admin on submission');

        // 2. SuperAdmin sees canAssignApprovalAdmin=true and ONLY Company A admins/superadmins in list
        $superShow = $this->actingOnlyAs($fixtureA['superAdmin'], 'superadmin')
            ->get(route('super.construction.projects.show', $fixtureA['project']->id));
        $superShow->assertOk();
        $superShow->assertInertia(fn ($page) => $page
            ->where('canAssignApprovalAdmin', true)
            ->has('companyApprovalAdmins', 3) // adminOne, adminTwo, superAdmin of Company A
            ->where('companyApprovalAdmins', function ($admins) use ($fixtureA, $fixtureB) {
                $ids = collect($admins)->pluck('key')->all();
                return in_array('member:' . $fixtureA['adminOne']->id, $ids, true)
                    && in_array('member:' . $fixtureA['adminTwo']->id, $ids, true)
                    && in_array('superadmin:' . $fixtureA['superAdmin']->id, $ids, true)
                    && !in_array('member:' . $fixtureA['draftsman']->id, $ids, true)
                    && !in_array('member:' . $fixtureB['adminOne']->id, $ids, true);
            })
        );

        // 3. Draftsman cannot assign an admin (403)
        $draftAssignRes = $this->actingOnlyAs($fixtureA['draftsman'], 'admin')
            ->post(route('super.construction.projects.drawing-approvals.assign-admin', $fixtureA['project']->id), [
                'drafting_job_id' => $fixtureA['draftingJob']->id,
                'assignee_key' => 'member:' . $fixtureA['adminOne']->id,
            ]);
        $draftAssignRes->assertStatus(403);

        // 4. Cross-company SuperAdmin cannot assign an admin (403)
        $crossCompanyRes = $this->actingOnlyAs($fixtureB['superAdmin'], 'superadmin')
            ->post(route('super.construction.projects.drawing-approvals.assign-admin', $fixtureA['project']->id), [
                'drafting_job_id' => $fixtureA['draftingJob']->id,
                'assignee_key' => 'member:' . $fixtureA['adminOne']->id,
            ]);
        $crossCompanyRes->assertStatus(403);

        // 5. Company A SuperAdmin cannot assign a Company B Admin (422 validation error)
        $crossAssigneeRes = $this->actingOnlyAs($fixtureA['superAdmin'], 'superadmin')
            ->post(route('super.construction.projects.drawing-approvals.assign-admin', $fixtureA['project']->id), [
                'drafting_job_id' => $fixtureA['draftingJob']->id,
                'assignee_key' => 'member:' . $fixtureB['adminOne']->id,
            ]);
        $crossAssigneeRes->assertSessionHasErrors('assignee_key');

        // 6. Revoking permission from Admin role hides ability & returns 403 for that Admin
        $perm = Permission::where('slug', 'drawing_approval.assign_admin')->firstOrFail();
        DB::table('construction_role_permissions')
            ->where('role_id', $fixtureA['cAdminRole']->id)
            ->where('permission_id', $perm->id)
            ->delete();

        $revokedShow = $this->actingOnlyAs($fixtureA['adminTwo'], 'admin')
            ->get(route('admin.construction.projects.show', $fixtureA['project']->id));
        $revokedShow->assertOk();
        $revokedShow->assertInertia(fn ($page) => $page
            ->where('canAssignApprovalAdmin', false)
        );

        $revokedPost = $this->actingOnlyAs($fixtureA['adminTwo'], 'admin')
            ->post(route('admin.construction.projects.drawing-approvals.assign-admin', $fixtureA['project']->id), [
                'drafting_job_id' => $fixtureA['draftingJob']->id,
                'assignee_key' => 'member:' . $fixtureA['adminOne']->id,
            ]);
        $revokedPost->assertStatus(403);
    }

    public function test_assigned_admin_sees_pending_action_on_admin_panel_login_and_clears_after_approval(): void
    {
        $this->seedCorePermissionsAndRoles();
        $fixture = $this->createCompanyFixture('flow');

        $revision = DrawingRevision::create([
            'project_id' => $fixture['project']->id,
            'drafting_job_id' => $fixture['draftingJob']->id,
            'revision_no' => 1,
            'drawing_type' => 'topographic',
            'drawing_no' => 'DWG-202',
            'uploaded_by_member_id' => $fixture['draftsman']->id,
            'uploaded_at' => now(),
            'status' => 'submitted',
        ]);

        $approval = DrawingApproval::create([
            'project_id' => $fixture['project']->id,
            'drawing_revision_id' => $revision->id,
            'requested_by_type' => Member::class,
            'requested_by_id' => $fixture['draftsman']->id,
            'requested_at' => now(),
            'decision' => 'pending',
        ]);

        $fixture['draftingJob']->update(['status' => 'submitted']);
        $fixture['project']->update(['current_stage' => 'drawing_approval_pending']);

        // SuperAdmin assigns approval specifically to adminOne
        $assignRes = $this->actingOnlyAs($fixture['superAdmin'], 'superadmin')
            ->from(route('super.construction.projects.show', $fixture['project']->id))
            ->post(route('super.construction.projects.drawing-approvals.assign-admin', $fixture['project']->id), [
                'drafting_job_id' => $fixture['draftingJob']->id,
                'assignee_key' => 'member:' . $fixture['adminOne']->id,
            ]);

        $assignRes->assertStatus(302);
        $assignRes->assertSessionHasNoErrors();

        $approval->refresh();
        $this->assertSame(Member::class, $approval->assigned_to_type);
        $this->assertSame((int) $fixture['adminOne']->id, (int) $approval->assigned_to_id);
        $this->assertNotNull($approval->assigned_at);

        // When assigned adminOne logs into Admin Panel dashboard, projects list & project show, pending_assigned_cad_approvals contains this item
        $adminOneDash = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->get(route('admin.construction.dashboard'));
        $adminOneDash->assertOk();
        $adminOneDash->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 1)
            ->where('pending_assigned_cad_approvals.0.project_id', $fixture['project']->id)
            ->where('pending_assigned_cad_approvals.0.drawing_no', 'DWG-202')
        );

        $adminOneIndex = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->get(route('admin.construction.projects.index'));
        $adminOneIndex->assertOk();
        $adminOneIndex->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 1)
            ->where('pending_assigned_cad_approvals.0.project_id', $fixture['project']->id)
        );

        $adminOneProjectShow = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->get(route('admin.construction.projects.show', $fixture['project']->id));
        $adminOneProjectShow->assertOk();
        $adminOneProjectShow->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 1)
            ->where('canAssignApprovalAdmin', true)
            ->has('companyApprovalAdmins', 3)
            ->where('project.current_stage', 'drawing_approval_pending')
            ->where('project.drawing_approvals.0.assigned_to.id', $fixture['adminOne']->id)
        );

        // Unassigned adminTwo does NOT see adminOne's assigned action on their Admin Panel dashboard
        $adminTwoDash = $this->actingOnlyAs($fixture['adminTwo'], 'admin')
            ->get(route('admin.construction.dashboard'));
        $adminTwoDash->assertOk();
        $adminTwoDash->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 0)
        );

        // AdminOne approves the CAD drawing via admin route
        $approveRes = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->from(route('admin.construction.projects.show', $fixture['project']->id))
            ->post(route('admin.construction.projects.drafting-jobs.review', [$fixture['project']->id, $fixture['draftingJob']->id]), [
                'decision' => 'approved',
                'remarks' => 'Verified and approved by assigned admin',
            ]);

        $approveRes->assertStatus(302);
        $approveRes->assertSessionHasNoErrors();

        $approval->refresh();
        $fixture['project']->refresh();
        $this->assertSame('approved', $approval->decision);
        $this->assertSame('ready_for_construction', $fixture['project']->current_stage);

        // Blinking pending action immediately clears from adminOne's Admin Panel dashboard
        $adminOneDashAfter = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->get(route('admin.construction.dashboard'));
        $adminOneDashAfter->assertOk();
        $adminOneDashAfter->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 0)
        );
    }

    public function test_company_admin_with_permission_can_assign_to_company_super_admin_and_super_admin_sees_pending_action(): void
    {
        $this->seedCorePermissionsAndRoles();
        $fixture = $this->createCompanyFixture('sa_assign');

        $revision = DrawingRevision::create([
            'project_id' => $fixture['project']->id,
            'drafting_job_id' => $fixture['draftingJob']->id,
            'revision_no' => 1,
            'drawing_type' => 'structural',
            'drawing_no' => 'DWG-303',
            'uploaded_by_member_id' => $fixture['draftsman']->id,
            'uploaded_at' => now(),
            'status' => 'submitted',
        ]);

        DrawingApproval::create([
            'project_id' => $fixture['project']->id,
            'drawing_revision_id' => $revision->id,
            'requested_by_type' => Member::class,
            'requested_by_id' => $fixture['draftsman']->id,
            'requested_at' => now(),
            'decision' => 'pending',
        ]);

        $fixture['draftingJob']->update(['status' => 'submitted']);
        $fixture['project']->update(['current_stage' => 'drawing_approval_pending']);

        // AdminOne (with drawing_approval.assign_admin permission) assigns to company SuperAdmin
        $res = $this->actingOnlyAs($fixture['adminOne'], 'admin')
            ->post(route('admin.construction.projects.drawing-approvals.assign-admin', $fixture['project']->id), [
                'drafting_job_id' => $fixture['draftingJob']->id,
                'assignee_key' => 'superadmin:' . $fixture['superAdmin']->id,
            ]);

        $res->assertStatus(302);
        $res->assertSessionHasNoErrors();

        $superDash = $this->actingOnlyAs($fixture['superAdmin'], 'superadmin')
            ->get(route('super.construction.dashboard'));
        $superDash->assertOk();
        $superDash->assertInertia(fn ($page) => $page
            ->has('pending_assigned_cad_approvals', 1)
            ->where('pending_assigned_cad_approvals.0.drawing_no', 'DWG-303')
        );
    }
}
