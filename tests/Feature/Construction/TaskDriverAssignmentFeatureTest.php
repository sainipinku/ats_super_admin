<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskDriverAssignmentFeatureTest extends TestCase
{
    public function test_react_projects_show_removes_assignment_role_select_while_retaining_driver_detection(): void
    {
        $filePath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        $this->assertStringNotContainsString(
            'Assignment Role',
            $content,
            'SuperAdmin Projects Show.jsx must not render Assignment Role select box in transfer modal'
        );
    }

    public function test_react_projects_show_identifies_driver_team_members(): void
    {
        $filePath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        // Verify team member driver detection exists
        $this->assertStringContainsString('is_driver', $content);
    }

    public function test_project_task_controller_handles_driver_role_in_transfer(): void
    {
        $filePath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($filePath);

        $content = file_get_contents($filePath);

        // Controller must explicitly handle driver role mapping
        $this->assertStringContainsString("'driver'", $content);
    }
}

