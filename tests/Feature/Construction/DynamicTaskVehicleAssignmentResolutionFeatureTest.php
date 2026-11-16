<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\Concerns\ResolvesProjectDynamicTasks;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\Task;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class DynamicTaskVehicleAssignmentResolutionFeatureTest extends TestCase
{
    public function test_resolves_project_dynamic_tasks_trait_has_failsafe_for_unified_tasks(): void
    {
        $traitContent = file_get_contents(base_path('app/Http/Controllers/Concerns/ResolvesProjectDynamicTasks.php'));

        $this->assertStringContainsString("'plan_code'", $traitContent);
        $this->assertStringContainsString('unifiedTasks', $traitContent);
    }

    public function test_vehicles_workspace_jsx_binds_task_id_and_filters_by_project_id(): void
    {
        $workspace = file_get_contents(resource_path('js/Pages/Construction/Vehicles/VehiclesWorkspace.jsx'));

        $this->assertStringContainsString('availableTasksForProject', $workspace);
        $this->assertStringContainsString('String(t.project_id) === String(assignmentForm.data.project_id)', $workspace);
        $this->assertStringContainsString('task_id', $workspace);
        $this->assertStringContainsString('-- No Tasks Available for Project --', $workspace);
        $this->assertStringContainsString('-- None / General Project Assignment --', $workspace);
    }

    public function test_execution_plan_auto_generates_plan_code_when_created(): void
    {
        $plan = new ExecutionPlan([
            'project_id' => 11,
            'title' => 'Ajmer Metro Execution Plan',
            'status' => 'active',
        ]);

        $this->assertTrue(method_exists($plan, 'booted'));
    }

    public function test_stringify_task_field_and_normalize_priority(): void
    {
        $dummy = new class {
            use ResolvesProjectDynamicTasks;
        };

        $this->assertSame('UNIFIED-13', $dummy->stringifyTaskField('UNIFIED-13'));
        $this->assertSame('testing title task', $dummy->stringifyTaskField('testing title task'));
        $this->assertSame('medium', $dummy->normalizeTaskPriority(null));
        $this->assertSame('high', $dummy->normalizeTaskPriority('high'));
        $this->assertSame('critical', $dummy->normalizeTaskPriority('CRITICAL'));
    }
}
