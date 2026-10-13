<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class MemberDashboardTasksTest extends TestCase
{
    use RefreshDatabase;

    private function createSuperAdmin(): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'roles' => json_encode(['super_admin']),
            'name' => 'Super Admin',
            'email' => 'admin-' . Str::random(5) . '@example.com',
            'phone' => '9999999999',
            'whatsapp_phone' => '9999999999',
            'password' => 'password',
        ]);
    }

    private function createMember(SuperAdmin $superAdmin, string $name, ?int $companyId = null): Member
    {
        $slug = Str::slug($name) . '-' . Str::lower(Str::random(5));

        return Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'company_id' => $companyId,
            'name' => $name,
            'username' => $slug,
            'email' => $slug . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
            'slug' => $slug,
        ]);
    }

    private function createProject(string $name, Company $company): Project
    {
        $client = \App\Models\Client::create([
            'company_id' => $company->id,
            'client_code' => 'CLI-' . strtoupper(Str::random(4)),
            'name' => $name . ' Client',
            'status' => 'active',
        ]);

        return Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . strtoupper(Str::random(4)),
            'slug' => Str::slug($name) . '-' . Str::lower(Str::random(4)),
            'name' => $name,
            'status' => 'active',
        ]);
    }

    public function test_member_dashboard_returns_today_tasks_and_tasks_with_inner_project_keys(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp', 'status' => 'active']);
        $project1 = $this->createProject('Site 1', $company);
        $project2 = $this->createProject('Site 2', $company);

        // Member IDs 1 and 2 are hardcoded in ConstructionAuthorizationService and Member models
        // as system owner / superadmin, so create seed members first so our test member is a regular worker.
        $this->createMember($admin, 'Owner Member', $company->id);
        $this->createMember($admin, 'SuperAdmin Member', $company->id);

        $member = $this->createMember($admin, 'John Worker', $company->id);
        $otherMember = $this->createMember($admin, 'Jane Other', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project1->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);
        ProjectTeamMember::create([
            'project_id' => $project2->id,
            'member_id' => $member->id,
            'is_primary' => false,
            'status' => 'active',
        ]);

        $today = now()->setTimezone('Asia/Kolkata')->startOfDay();

        // 1. Unified task assigned to member on project 1 for today
        $unifiedTask1 = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Unified Today Task',
            'project_id' => $project1->id,
            'status' => 'in_progress',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $member->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $unifiedTask1->id,
            'assigned_to' => $member->id,
            'project_id' => $project1->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        // 2. Legacy task assigned to member on project 2
        $plan = \App\Models\ExecutionPlan::create([
            'project_id' => $project2->id,
            'plan_code' => 'EXP-00001',
            'title' => 'Execution Plan',
            'status' => 'planned',
        ]);
        $legacyTask = ExecutionTask::create([
            'task_code' => 'EXT-00001',
            'title' => 'Legacy Assigned Task',
            'project_id' => $project2->id,
            'execution_plan_id' => $plan->id,
            'status' => 'planned',
            'planned_start_date' => $today->copy(),
            'planned_end_date' => $today->copy()->addDays(2),
        ]);
        ExecutionTaskAssignee::create([
            'execution_task_id' => $legacyTask->id,
            'member_id' => $member->id,
            'project_id' => $project2->id,
            'status' => 'active',
        ]);

        // 3. Task assigned only to other member (should NOT appear in member's dashboard)
        $otherTask = Task::create([
            'uuid' => (string) Str::uuid(),
            'title' => 'Other Member Task',
            'project_id' => $project1->id,
            'status' => 'planned',
            'start_date' => $today->copy(),
            'end_date' => $today->copy(),
            'created_by' => $otherMember->id,
        ]);
        TaskAssignment::create([
            'uuid' => (string) Str::uuid(),
            'task_id' => $otherTask->id,
            'assigned_to' => $otherMember->id,
            'project_id' => $project1->id,
            'status' => 'active',
            'assigned_by' => $admin->id,
            'assigned_by_type' => 'super_admin',
        ]);

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');

        $response->assertOk();
        $response->assertJsonPath('success', true);

        // Verify top-level keys
        $responseData = $response->json();

        $this->assertArrayHasKey('today_tasks', $responseData);
        $this->assertArrayHasKey('today_task', $responseData);
        $this->assertArrayHasKey('todays_tasks', $responseData);
        $this->assertArrayHasKey('tasks', $responseData);
        $this->assertArrayHasKey('task', $responseData);
        $this->assertArrayHasKey('all_tasks', $responseData);

        // Both assigned tasks should be in tasks list
        $taskTitles = collect($responseData['tasks'])->pluck('title')->all();
        $this->assertContains('Unified Today Task', $taskTitles);
        $this->assertContains('Legacy Assigned Task', $taskTitles);
        $this->assertNotContains('Other Member Task', $taskTitles);

        // Check inner project keys for projects array
        $this->assertNotEmpty($responseData['projects']);
        foreach ($responseData['projects'] as $proj) {
            $this->assertArrayHasKey('today_task', $proj);
            $this->assertArrayHasKey('today_tasks', $proj);
            $this->assertArrayHasKey('task', $proj);
            $this->assertArrayHasKey('tasks', $proj);

            if ($proj['id'] === $project1->id) {
                $projTaskTitles = collect($proj['tasks'])->pluck('title')->all();
                $this->assertContains('Unified Today Task', $projTaskTitles);
                $this->assertNotContains('Legacy Assigned Task', $projTaskTitles);
            }
            if ($proj['id'] === $project2->id) {
                $projTaskTitles = collect($proj['tasks'])->pluck('title')->all();
                $this->assertContains('Legacy Assigned Task', $projTaskTitles);
                $this->assertNotContains('Unified Today Task', $projTaskTitles);
            }
        }

        // Check inner project keys for current_project
        $this->assertNotNull($responseData['current_project']);
        $this->assertArrayHasKey('today_task', $responseData['current_project']);
        $this->assertArrayHasKey('today_tasks', $responseData['current_project']);
        $this->assertArrayHasKey('task', $responseData['current_project']);
        $this->assertArrayHasKey('tasks', $responseData['current_project']);
    }

    public function test_tasks_list_is_capped_at_last_20(): void
    {
        $admin = $this->createSuperAdmin();
        $company = Company::create(['name' => 'Acme Corp 2', 'status' => 'active']);
        $project = $this->createProject('Site 20', $company);
        $member = $this->createMember($admin, 'Worker 20', $company->id);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        // Create 25 tasks assigned to this member
        for ($i = 1; $i <= 25; $i++) {
            $task = Task::create([
                'uuid' => (string) Str::uuid(),
                'title' => "Task Number {$i}",
                'project_id' => $project->id,
                'status' => 'planned',
                'created_by' => $member->id,
            ]);
            TaskAssignment::create([
                'uuid' => (string) Str::uuid(),
                'task_id' => $task->id,
                'assigned_to' => $member->id,
                'project_id' => $project->id,
                'status' => 'active',
                'assigned_by' => $admin->id,
                'assigned_by_type' => 'super_admin',
            ]);
        }

        $response = $this->actingAs($member, 'sanctum')->getJson('/api/member/dashboard');
        $response->assertOk();

        $responseData = $response->json();
        // Top-level tasks must be capped at 20
        $this->assertCount(20, $responseData['tasks']);
        $this->assertCount(20, $responseData['task']);
        $this->assertCount(20, $responseData['all_tasks']);

        // Inner project tasks must also be capped at 20
        $this->assertCount(20, $responseData['projects'][0]['tasks']);
        $this->assertCount(20, $responseData['projects'][0]['task']);
    }
}
