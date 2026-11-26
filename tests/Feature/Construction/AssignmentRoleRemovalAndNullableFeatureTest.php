<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\Company;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\SuperAdmin;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Services\Construction\ConstructionExecutionService;
use App\Services\Construction\TaskManagementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class AssignmentRoleRemovalAndNullableFeatureTest extends TestCase
{
    use RefreshDatabase;

    private function createFixture(): array
    {
        $company = Company::create([
            'name' => 'Nullable Role Co ' . Str::random(4),
            'status' => 'active',
        ]);

        $superAdmin = SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'SA ' . Str::random(5),
            'email' => 'sa_ar_' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'),
            'status' => 1,
            'company_id' => $company->id,
            'roles' => ['super_admin'],
        ]);

        $client = Client::create([
            'company_id' => $company->id,
            'name' => 'Client ' . Str::random(4),
            'client_code' => 'CLI-' . Str::upper(Str::random(5)),
            'status' => 'active',
        ]);

        $project = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'name' => 'Project ' . Str::random(5),
            'slug' => 'proj-' . Str::lower(Str::random(6)),
            'project_code' => 'PRJ-' . Str::upper(Str::random(5)),
            'status' => 'active',
        ]);

        $member = Member::create([
            'uuid' => (string) Str::uuid(),
            'created_by' => $superAdmin->id,
            'name' => 'Team Member ' . Str::random(4),
            'username' => 'tm_' . Str::lower(Str::random(6)),
            'slug' => 'tm-' . Str::lower(Str::random(6)),
            'email' => 'tm_' . Str::lower(Str::random(6)) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => Hash::make('password123'),
            'status' => Member::STATUS_ACTIVE,
            'roles' => [1],
        ]);

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $member->id,
            'status' => 'active',
        ]);

        $plan = ExecutionPlan::create([
            'project_id' => $project->id,
            'plan_code' => 'EXP-' . Str::upper(Str::random(5)),
            'title' => 'Execution Plan',
            'status' => 'planned',
        ]);

        return compact('company', 'superAdmin', 'client', 'project', 'member', 'plan');
    }

    public function test_assignment_role_columns_are_nullable_with_null_default_in_database(): void
    {
        foreach (['task_assignments', 'construction_execution_task_assignees'] as $table) {
            $cols = collect(Schema::getColumns($table))->keyBy('name');
            $this->assertTrue($cols->has('assignment_role'), "{$table} must have assignment_role column");

            $col = $cols->get('assignment_role');
            $this->assertTrue((bool) $col['nullable'], "{$table}.assignment_role must be nullable");
            $this->assertTrue(
                in_array($col['default'], [null, 'NULL'], true),
                "{$table}.assignment_role default must be null"
            );
        }
    }

    public function test_models_persist_null_assignment_role_when_omitted(): void
    {
        $fx = $this->createFixture();

        $unifiedTask = Task::create([
            'project_id' => $fx['project']->id,
            'task_code' => 'TSK-NULL-' . Str::upper(Str::random(4)),
            'title' => 'Unified Nullable Task',
            'status' => 'planned',
            'sort_order' => 1,
        ]);

        $taskAssignment = TaskAssignment::create([
            'task_id' => $unifiedTask->id,
            'project_id' => $fx['project']->id,
            'assigned_to' => $fx['member']->id,
            'assigned_by' => $fx['superAdmin']->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->assertNull($taskAssignment->fresh()->assignment_role);
        $this->assertDatabaseHas('task_assignments', [
            'id' => $taskAssignment->id,
            'assignment_role' => null,
        ]);

        $execTask = ExecutionTask::create([
            'project_id' => $fx['project']->id,
            'execution_plan_id' => $fx['plan']->id,
            'task_code' => 'EXT-NULL-' . Str::upper(Str::random(4)),
            'title' => 'Legacy Nullable Task',
            'priority' => 'medium',
            'status' => 'planned',
        ]);

        $execAssignee = ExecutionTaskAssignee::create([
            'project_id' => $fx['project']->id,
            'execution_task_id' => $execTask->id,
            'member_id' => $fx['member']->id,
            'is_primary' => true,
            'status' => 'active',
        ]);

        $this->assertNull($execAssignee->fresh()->assignment_role);
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'id' => $execAssignee->id,
            'assignment_role' => null,
        ]);
    }

    public function test_transfer_task_endpoint_succeeds_without_assignment_role_and_stores_null(): void
    {
        $fx = $this->createFixture();

        $unifiedTask = Task::create([
            'project_id' => $fx['project']->id,
            'task_code' => 'TSK-TRN-' . Str::upper(Str::random(4)),
            'title' => 'Transfer Without Role',
            'status' => 'planned',
            'sort_order' => 1,
        ]);

        $execTask = ExecutionTask::create([
            'id' => $unifiedTask->id,
            'project_id' => $fx['project']->id,
            'execution_plan_id' => $fx['plan']->id,
            'task_code' => $unifiedTask->task_code,
            'title' => $unifiedTask->title,
            'priority' => 'medium',
            'status' => 'planned',
        ]);

        $response = $this->actingAs($fx['superAdmin'], 'superadmin')
            ->post(route('super.construction.projects.tasks-v2.transfer', [
                'project' => $fx['project']->id,
                'taskId' => $unifiedTask->id,
            ]), [
                'member_id' => $fx['member']->id,
                'due_date' => now()->addDays(3)->toDateString(),
                'notes' => 'Transferred without assignment_role field',
            ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect();

        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $unifiedTask->id,
            'assigned_to' => $fx['member']->id,
            'assignment_role' => null,
            'status' => 'active',
        ]);

        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'execution_task_id' => $execTask->id,
            'member_id' => $fx['member']->id,
            'assignment_role' => null,
            'status' => 'active',
        ]);
    }

    public function test_execution_and_task_services_store_null_assignment_role_when_omitted(): void
    {
        $fx = $this->createFixture();

        $execTask = ExecutionTask::create([
            'project_id' => $fx['project']->id,
            'execution_plan_id' => $fx['plan']->id,
            'task_code' => 'EXT-SVC-' . Str::upper(Str::random(4)),
            'title' => 'Execution Service Assign',
            'priority' => 'medium',
            'status' => 'planned',
        ]);

        /** @var ConstructionExecutionService $execService */
        $execService = app(ConstructionExecutionService::class);
        $assignee = $execService->assignTask($execTask, [
            'member_id' => $fx['member']->id,
            'is_primary' => true,
        ], $fx['superAdmin']);

        $this->assertNull($assignee->assignment_role);
        $this->assertDatabaseHas('construction_execution_task_assignees', [
            'id' => $assignee->id,
            'assignment_role' => null,
        ]);

        $unifiedTask = Task::create([
            'project_id' => $fx['project']->id,
            'task_code' => 'TSK-SVC-' . Str::upper(Str::random(4)),
            'title' => 'Task Service Replace Assignments',
            'status' => 'planned',
            'sort_order' => 2,
        ]);

        /** @var TaskManagementService $taskService */
        $taskService = app(TaskManagementService::class);
        $taskService->replaceAssignments($unifiedTask, [$fx['member']->id], $fx['superAdmin']);

        $this->assertSame(1, TaskAssignment::where('task_id', $unifiedTask->id)->count());
        $this->assertDatabaseHas('task_assignments', [
            'task_id' => $unifiedTask->id,
            'assigned_to' => $fx['member']->id,
            'assignment_role' => null,
        ]);
    }

    public function test_compiled_vite_assets_omit_assignment_role_modal_dropdown(): void
    {
        $assetFiles = glob(public_path('build/assets/*.js')) ?: [];
        $this->assertNotEmpty($assetFiles);

        foreach ($assetFiles as $file) {
            $js = file_get_contents($file);
            if (is_string($js)) {
                $this->assertStringNotContainsString(
                    'Field Executor (Site Work / Operations)',
                    $js,
                    'Compiled JS must not contain removed Assignment Role dropdown option'
                );
            }
        }
    }
}
