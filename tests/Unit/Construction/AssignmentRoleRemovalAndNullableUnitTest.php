<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class AssignmentRoleRemovalAndNullableUnitTest extends TestCase
{
    public function test_super_admin_projects_show_removes_assignment_role_from_transfer_modal(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            'Assignment Role',
            $content,
            'SuperAdmin Projects/Show.jsx must not display Assignment Role label in Assign / Transfer Task modal'
        );
        $this->assertStringNotContainsString(
            'transferForm.data.assignment_role',
            $content,
            'SuperAdmin Projects/Show.jsx must not bind transferForm.data.assignment_role'
        );
        $this->assertStringNotContainsString(
            'Field Executor (Site Work / Operations)',
            $content,
            'SuperAdmin Projects/Show.jsx must not render Assignment Role dropdown options'
        );
        $this->assertStringContainsString(
            'Assign / Transfer Task',
            $content,
            'SuperAdmin Projects/Show.jsx must retain Assign / Transfer Task modal'
        );
        $this->assertStringContainsString(
            'Target Due Date',
            $content,
            'SuperAdmin Projects/Show.jsx must retain Target Due Date field in transfer modal'
        );
    }

    public function test_execution_workspace_and_admin_show_remove_assignment_role_input_and_guard_nullable_display(): void
    {
        $workspacePath = resource_path('js/Pages/Construction/Execution/ExecutionWorkspace.jsx');
        $this->assertFileExists($workspacePath);
        $workspaceContent = file_get_contents($workspacePath);

        $this->assertStringNotContainsString(
            'label="Assignment Role"',
            $workspaceContent,
            'ExecutionWorkspace.jsx must not render Assignment Role input field'
        );
        $this->assertStringNotContainsString(
            'assignment_role: "worker"',
            $workspaceContent,
            'ExecutionWorkspace.jsx must not default assignmentForm assignment_role to worker'
        );
        $this->assertStringNotContainsString(
            '• {assignee.assignment_role}',
            $workspaceContent,
            'ExecutionWorkspace.jsx must guard nullable assignment_role before rendering separator bullet'
        );

        $adminShowPath = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $this->assertFileExists($adminShowPath);
        $adminShowContent = file_get_contents($adminShowPath);

        $this->assertStringNotContainsString(
            '• {assignment.assignment_role}',
            $adminShowContent,
            'Admin Projects/Show.jsx must guard nullable assignment_role before rendering separator bullet'
        );
    }

    public function test_migration_makes_assignment_role_nullable_on_task_tables(): void
    {
        $migrationPath = database_path('migrations/2026_11_26_000002_make_assignment_role_nullable_on_task_tables.php');
        $this->assertFileExists($migrationPath);

        $content = file_get_contents($migrationPath);

        $this->assertStringContainsString('task_assignments', $content);
        $this->assertStringContainsString('construction_execution_task_assignees', $content);
        $this->assertStringContainsString('->nullable()', $content);
    }

    public function test_backend_transfer_and_assignment_services_preserve_null_assignment_role(): void
    {
        $controllerContent = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));
        $this->assertStringNotContainsString(
            "\$validated['assignment_role'] ?? 'executor'",
            $controllerContent,
            'ProjectTaskController::transferTask must not default omitted assignment_role to executor'
        );

        $executionSvcContent = file_get_contents(app_path('Services/Construction/ConstructionExecutionService.php'));
        $this->assertStringNotContainsString(
            "\$validated['primary_assignment_role'] ?? 'worker'",
            $executionSvcContent,
            'ConstructionExecutionService must not default omitted primary_assignment_role to worker'
        );
        $this->assertStringNotContainsString(
            "\$validated['assignment_role'] ?? 'worker'",
            $executionSvcContent,
            'ConstructionExecutionService must not default omitted assignment_role to worker'
        );
    }
}
