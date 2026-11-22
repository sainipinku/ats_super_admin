<?php

namespace Tests\Feature\Construction;

use App\Models\Company;
use App\Models\Member;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardDynamicScopingFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_dashboard_scopes_projects_and_employees_dynamically_to_company_superadmin(): void
    {
        // 1. Create Company A with 1 project and 1 employee
        $companyA = Company::create(['name' => 'Company A ' . Str::random(5), 'status' => 'active']);
        $superAdminA = SuperAdmin::create([
            'name' => 'SuperAdmin A',
            'email' => 'sa_a_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $companyA->id,
            'is_active' => true,
        ]);

        $projectA = Project::create([
            'company_id' => $companyA->id,
            'project_code' => 'PRJ-A-' . Str::upper(Str::random(4)),
            'name' => 'Project Company A',
            'slug' => 'prj-a-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $memberA = Member::create([
            'name' => 'Employee A',
            'username' => 'emp_a_' . Str::random(6),
            'slug' => 'emp-a-' . Str::lower(Str::random(6)),
            'email' => 'emp_a_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'created_by' => $superAdminA->id,
            'company_name' => $companyA->name,
            'status' => Member::STATUS_ACTIVE,
        ]);

        // 2. Create Company B with 2 projects and 2 employees
        $companyB = Company::create(['name' => 'Company B ' . Str::random(5), 'status' => 'active']);
        $superAdminB = SuperAdmin::create([
            'name' => 'SuperAdmin B',
            'email' => 'sa_b_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'company_id' => $companyB->id,
            'is_active' => true,
        ]);

        $projectB1 = Project::create([
            'company_id' => $companyB->id,
            'project_code' => 'PRJ-B1-' . Str::upper(Str::random(4)),
            'name' => 'Project 1 Company B',
            'slug' => 'prj-b1-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        $memberB1 = Member::create([
            'name' => 'Employee B1',
            'username' => 'emp_b1_' . Str::random(6),
            'slug' => 'emp-b1-' . Str::lower(Str::random(6)),
            'email' => 'emp_b1_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'created_by' => $superAdminB->id,
            'company_name' => $companyB->name,
            'status' => Member::STATUS_ACTIVE,
        ]);
        $memberB2 = Member::create([
            'name' => 'Employee B2',
            'username' => 'emp_b2_' . Str::random(6),
            'slug' => 'emp-b2-' . Str::lower(Str::random(6)),
            'email' => 'emp_b2_' . Str::random(6) . '@example.com',
            'password' => bcrypt('password'),
            'created_by' => $superAdminB->id,
            'company_name' => $companyB->name,
            'status' => Member::STATUS_ACTIVE,
        ]);

        // 3. Authenticate as SuperAdmin A and request dashboard
        $responseA = $this->actingAs($superAdminA, 'superadmin')
            ->get(route('super.construction.dashboard'));

        $responseA->assertOk();
        $propsA = $responseA->original->getData()['page']['props'];

        // Assert Company A only sees 1 project (Project A) and 1 employee (Employee A)
        $this->assertEquals(1, $propsA['stats']['projects']['total']);
        $this->assertEquals(1, $propsA['stats']['employees']['total']);
        $this->assertCount(1, $propsA['recentProjects']);
        $this->assertEquals($projectA->id, $propsA['recentProjects'][0]['id']);

        // 4. Authenticate as SuperAdmin B and request dashboard
        $responseB = $this->actingAs($superAdminB, 'superadmin')
            ->get(route('super.construction.dashboard'));

        $responseB->assertOk();
        $propsB = $responseB->original->getData()['page']['props'];

        $this->assertEquals(1, $propsB['stats']['projects']['total']);
        $this->assertEquals(2, $propsB['stats']['employees']['total']);
    }

    public function test_project_owner_can_filter_dashboard_dynamically_by_company_id(): void
    {
        $rootOwner = SuperAdmin::find(1);
        if (!$rootOwner) {
            $rootOwner = SuperAdmin::create([
                'id' => 1,
                'name' => 'Root Owner',
                'email' => 'superadmin@gmail.com',
                'password' => bcrypt('password'),
                'is_active' => true,
                'is_project_owner' => 1,
            ]);
        }

        $companyC = Company::create(['name' => 'Company C ' . Str::random(5), 'status' => 'active']);
        $projectC = Project::create([
            'company_id' => $companyC->id,
            'project_code' => 'PRJ-C-' . Str::upper(Str::random(4)),
            'name' => 'Project Company C',
            'slug' => 'prj-c-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'current_stage' => 'planning',
        ]);

        // Query with ?company_id=...
        $response = $this->actingAs($rootOwner, 'superadmin')
            ->get(route('super.construction.dashboard', ['company_id' => $companyC->id]));

        $response->assertOk();
        $props = $response->original->getData()['page']['props'];

        $this->assertEquals(1, $props['stats']['projects']['total']);
        $this->assertEquals($companyC->id, $props['selected_company_id']);
        $this->assertEquals($projectC->id, $props['recentProjects'][0]['id']);
    }
}
