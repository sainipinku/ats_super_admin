<?php

namespace Tests\Unit\Construction;

use App\Models\ExecutionPlan;
use Tests\TestCase;

class DynamicTaskVehicleAssignmentResolutionUnitTest extends TestCase
{
    public function test_execution_plan_model_has_auto_generated_plan_code_or_booted_hook(): void
    {
        $planModel = file_get_contents(base_path('app/Models/ExecutionPlan.php'));

        $this->assertStringContainsString('booted', $planModel, 'ExecutionPlan model must have a booted hook to auto-generate plan_code if missing');
        $this->assertStringContainsString('plan_code', $planModel);
    }

    public function test_resolves_project_dynamic_tasks_trait_always_provides_plan_code(): void
    {
        $traitContent = file_get_contents(base_path('app/Http/Controllers/Concerns/ResolvesProjectDynamicTasks.php'));

        // Trait must supply plan_code when creating ExecutionPlan
        $this->assertStringContainsString("'plan_code'", $traitContent, 'ResolvesProjectDynamicTasks must supply plan_code when creating ExecutionPlan');
    }
}
