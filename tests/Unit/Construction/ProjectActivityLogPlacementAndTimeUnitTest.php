<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectActivityLogPlacementAndTimeUnitTest extends TestCase
{
    public function test_superadmin_project_show_places_activity_log_at_the_bottom_below_execution_tasks_and_checklists(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $content = file_get_contents($showPath);

        $activityLogPos = strpos($content, '<ProjectActivityLog');
        $this->assertNotFalse($activityLogPos, 'SuperAdmin Show.jsx must render <ProjectActivityLog');

        $executionTasksPos = strpos($content, 'ref={executionTasksRef}');
        $this->assertNotFalse($executionTasksPos, 'SuperAdmin Show.jsx must render execution tasks');

        $checklistPos = strpos($content, '<DynamicChecklistManager');
        $this->assertNotFalse($checklistPos, 'SuperAdmin Show.jsx must render <DynamicChecklistManager');

        // Activity Log must be shifted to the bottom (below both Execution Tasks and Dynamic Checklist Manager)
        $this->assertGreaterThan(
            $executionTasksPos,
            $activityLogPos,
            'Activity Log must be placed after Execution Tasks'
        );

        $this->assertGreaterThan(
            $checklistPos,
            $activityLogPos,
            'Activity Log must be placed after Dynamic Checklist Manager'
        );
    }

    public function test_project_activity_log_renders_exact_time_alongside_relative_time(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx');
        $this->assertFileExists($componentPath);

        $content = file_get_contents($componentPath);

        // formatTimeOnly or formatTime function must exist
        $this->assertMatchesRegularExpression(
            '/function\s+formatTimeOnly\(/',
            $content,
            'ProjectActivityLog.jsx must define a function formatTimeOnly for exact time rendering'
        );

        // Must display formatTimeOnly in item timestamp row
        $this->assertStringContainsString(
            'formatTimeOnly(item.created_at)',
            $content,
            'ProjectActivityLog.jsx must render formatTimeOnly(item.created_at)'
        );
    }
}
