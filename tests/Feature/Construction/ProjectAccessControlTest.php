<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Models\Client;
use App\Models\Company;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\Role;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectAccessControlTest extends TestCase
{
    use RefreshDatabase;

    private $consumer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->consumer = new class {
            use ResolvesConstructionActor;
            public function testGetAccessibleProjectIds($actor) {
                return $this->getAccessibleProjectIds($actor);
            }
        };
    }

    private function createCompany(string $name): Company
    {
        return Company::create([
            'name' => $name,
            'status' => 'active',
        ]);
    }

    private function createProject(Company $company, string $code, string $name): Project
    {
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => $code . '-CLI',
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => $code,
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => 'active',
            'current_stage' => 'survey_planned',
        ]);
    }

    public function test_global_super_admin_sees_all_projects_across_companies(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $project1 = $this->createProject($company1, 'PRJ-1', 'Project 1');
        $project2 = $this->createProject($company2, 'PRJ-2', 'Project 2');

        $globalSuperAdmin = SuperAdmin::create([
            'id' => 1,
            'uuid' => (string) Str::uuid(),
            'name' => 'Root Super Admin',
            'email' => 'superadmin@example.com',
            'password' => 'password',
            'status' => 1,
        ]);

        $projectIds = $this->consumer->testGetAccessibleProjectIds($globalSuperAdmin);

        $this->assertCount(2, $projectIds);
        $this->assertTrue($projectIds->contains($project1->id));
        $this->assertTrue($projectIds->contains($project2->id));
    }

    public function test_company_super_admin_owner_sees_only_own_company_projects(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $project1 = $this->createProject($company1, 'PRJ-1', 'Project 1');
        $project2 = $this->createProject($company2, 'PRJ-2', 'Project 2');

        $companyOwner = SuperAdmin::create([
            'id' => 5,
            'company_id' => $company1->id,
            'uuid' => (string) Str::uuid(),
            'name' => 'Company One Owner',
            'email' => 'owner1@example.com',
            'password' => 'password',
            'status' => 1,
        ]);

        $projectIds = $this->consumer->testGetAccessibleProjectIds($companyOwner);

        $this->assertCount(1, $projectIds);
        $this->assertTrue($projectIds->contains($project1->id));
        $this->assertFalse($projectIds->contains($project2->id));
    }

    public function test_company_admin_member_sees_all_own_company_projects(): void
    {
        $company1 = $this->createCompany('Company One');
        $company2 = $this->createCompany('Company Two');

        $project1 = $this->createProject($company1, 'PRJ-1', 'Project 1');
        $project1b = $this->createProject($company1, 'PRJ-1B', 'Project 1B');
        $project2 = $this->createProject($company2, 'PRJ-2', 'Project 2');

        $adminRole = Role::create([
            'id' => 9,
            'name' => 'Admin',
            'slug' => 'admin',
            'status' => 1,
            'created_by' => 1,
        ]);

        $companyAdminMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Company Admin Member',
            'username' => 'admin_user',
            'email' => 'admin@company1.com',
            'company_name' => $company1->name,
            'created_by' => 1,
            'password' => 'password',
            'roles' => [$adminRole->id],
            'slug' => 'admin-user',
            'status' => 1,
        ]);

        $projectIds = $this->consumer->testGetAccessibleProjectIds($companyAdminMember);

        $this->assertCount(2, $projectIds);
        $this->assertTrue($projectIds->contains($project1->id));
        $this->assertTrue($projectIds->contains($project1b->id));
        $this->assertFalse($projectIds->contains($project2->id));
    }

    public function test_regular_unassigned_member_sees_zero_projects(): void
    {
        $company1 = $this->createCompany('Company One');
        $this->createProject($company1, 'PRJ-1', 'Project 1');

        $memberRole = Role::create([
            'id' => 1,
            'name' => 'Member',
            'slug' => 'member',
            'status' => 1,
            'created_by' => 1,
        ]);

        $regularMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Field Worker',
            'username' => 'worker_1',
            'email' => 'worker@company1.com',
            'company_id' => $company1->id,
            'created_by' => 1,
            'password' => 'password',
            'roles' => [1],
            'registration_source' => 'admin_created',
            'slug' => 'worker-1',
            'status' => 1,
        ]);

        $projectIds = $this->consumer->testGetAccessibleProjectIds($regularMember);

        $this->assertCount(0, $projectIds);
    }

    public function test_regular_member_assigned_to_project_sees_only_assigned_project(): void
    {
        $company1 = $this->createCompany('Company One');
        $project1 = $this->createProject($company1, 'PRJ-1', 'Project 1');
        $project2 = $this->createProject($company1, 'PRJ-2', 'Project 2');

        $memberRole = Role::create([
            'id' => 1,
            'name' => 'Member',
            'slug' => 'member',
            'status' => 1,
            'created_by' => 1,
        ]);

        $surveyorRole = ConstructionRole::create([
            'name' => 'Surveyor',
            'slug' => 'surveyor',
            'status' => 'active',
        ]);

        $assignedMember = Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Surveyor John',
            'username' => 'surveyor_john',
            'email' => 'john@company1.com',
            'company_id' => $company1->id,
            'created_by' => 1,
            'password' => 'password',
            'roles' => [1],
            'registration_source' => 'admin_created',
            'slug' => 'surveyor-john',
            'status' => 1,
        ]);

        // Assign John ONLY to Project 1
        ProjectTeamMember::create([
            'project_id' => $project1->id,
            'member_id' => $assignedMember->id,
            'role_id' => $surveyorRole->id,
            'status' => 'active',
        ]);

        $projectIds = $this->consumer->testGetAccessibleProjectIds($assignedMember);

        $this->assertCount(1, $projectIds);
        $this->assertTrue($projectIds->contains($project1->id));
        $this->assertFalse($projectIds->contains($project2->id));
    }
}
