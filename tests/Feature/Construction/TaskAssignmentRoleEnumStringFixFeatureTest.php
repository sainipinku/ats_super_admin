<?php

namespace Tests\Feature\Construction;

use App\Enums\TaskAssignmentRole;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class TaskAssignmentRoleEnumStringFixFeatureTest extends TestCase
{
    public function test_controller_safely_extracts_role_from_enum_without_string_conversion_error(): void
    {
        $controllerContent = file_get_contents(base_path('app/Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));

        $this->assertStringNotContainsString(
            '(string) ($asg->assignment_role',
            $controllerContent
        );

        $this->assertMatchesRegularExpression(
            '/BackedEnum|\$asg->assignment_role\?->value/',
            $controllerContent,
            'ProjectTaskController must handle BackedEnum instances or use ->value for assignment_role'
        );
    }

    public function test_task_assignments_with_enum_can_be_evaluated_for_driver_and_surveyor(): void
    {
        $asgExecutor = new TaskAssignment([
            'assignment_role' => TaskAssignmentRole::EXECUTOR,
        ]);

        $asgSurveyor = new TaskAssignment([
            'assignment_role' => TaskAssignmentRole::SURVEYOR,
        ]);

        $this->assertInstanceOf(TaskAssignmentRole::class, $asgExecutor->assignment_role);
        $this->assertInstanceOf(TaskAssignmentRole::class, $asgSurveyor->assignment_role);

        $this->assertSame('executor', $asgExecutor->assignment_role->value);
        $this->assertSame('surveyor', $asgSurveyor->assignment_role->value);
    }
}
