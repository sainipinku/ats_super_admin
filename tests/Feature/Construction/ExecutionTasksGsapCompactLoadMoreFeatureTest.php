<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\Project;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExecutionTasksGsapCompactLoadMoreFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_project_show_endpoint_renders_more_than_ten_execution_tasks_for_load_more_pagination(): void
    {
        $company = Company::create([
            'name' => 'Execution Tasks Co ' . Str::random(5),
            'status' => 'active',
        ]);
        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'company_id' => $company->id,
            'roles' => json_encode(['super_admin']),
            'name' => 'Super Admin Execution UI',
            'email' => 'sa-et-' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => (string) random_int(7000000000, 9999999999),
            'whatsapp_phone' => (string) random_int(7000000000, 9999999999),
            'password' => 'password',
        ]);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(6)),
            'name' => 'Execution Tasks Client',
            'status' => 'active',
        ]);
        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-' . Str::upper(Str::random(6)),
            'name' => 'Execution Tasks Pagination Project',
            'slug' => 'exec-tasks-gsap-' . Str::lower(Str::random(6)),
            'status' => 'active',
        ]);
        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . Str::upper(Str::random(6)),
            'title' => 'Master Execution Plan',
            'status' => 'planned',
        ]);

        for ($i = 1; $i <= 12; $i++) {
            ExecutionTask::create([
                'project_id' => $project->id,
                'execution_plan_id' => $plan->id,
                'task_code' => sprintf('TSK-%05d', $i),
                'title' => "Execution Task Batch Item #{$i}",
                'priority' => $i % 2 === 0 ? 'high' : 'medium',
                'progress_percent' => $i <= 6 ? 100 : 25,
                'status' => $i <= 6 ? 'completed' : 'in_progress',
            ]);
        }

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.projects.show', $project->id));

        $response->assertStatus(200);
        $response->assertSee('Execution Task Batch Item #1', false);
        $response->assertSee('Execution Task Batch Item #12', false);
    }

    public function test_superadmin_and_admin_execution_tasks_preserve_core_controls_and_reset_pagination(): void
    {
        $superSource = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminSource = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('setVisibleTaskCount(TASKS_PER_PAGE)', $superSource);
        $this->assertStringContainsString('remainingTasks', $superSource);
        $this->assertStringContainsString('prefers-reduced-motion', $superSource);
        $this->assertStringContainsString('AssignToDraftsmanButton', $superSource);
        $this->assertStringContainsString('Filter Tasks By Assigned Role', $superSource);

        $this->assertStringContainsString('remainingTasks', $adminSource);
        $this->assertStringContainsString('AssignToDraftsmanButton', $adminSource);
    }
}
