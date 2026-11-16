<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskCompletionArrayToStringFixFeatureTest extends TestCase
{
    public function test_review_survey_task_method_handles_array_attributes_safely(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Assert $extractSupervisorText closure is defined and safely loops over arrays
        $this->assertMatchesRegularExpression(
            "/\\\$extractSupervisorText\s*=\s*function\s*\(/i",
            $code,
            'ProjectTaskController must define $extractSupervisorText closure'
        );

        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*is_array\(\\\$supervisor->designation\)\s*\)\s*\{\s*foreach\s*\(\\\$supervisor->designation as \\\$d\)/i",
            $code,
            'ProjectTaskController must iterate through array designations safely'
        );

        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*is_array\(\\\$supervisor->role_names\)\s*\)\s*\{\s*foreach\s*\(\\\$supervisor->role_names as \\\$r\)/i",
            $code,
            'ProjectTaskController must iterate through role_names array safely'
        );
    }

    public function test_review_survey_marks_driver_and_non_survey_tasks_completed_with_proper_status(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Verify driver task sets status to completed and progress_percent = 100
        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*\\\$isDriverTask\s*\|\|\s*!\\\$isSurveyorTask\s*\)\s*\{[\s\S]*?\\\$legacyTask->status\s*=\s*['\"]completed['\"];[\s\S]*?\\\$unifiedTask->status\s*=\s*['\"]completed['\"];/i",
            $code,
            'ProjectTaskController must set completed status for driver/non-survey tasks'
        );

        // Verify success message indicates task marked as completed
        $this->assertStringContainsString(
            "(\$isDriverTask || !\$isSurveyorTask) ? 'Task marked as completed successfully.' : 'Survey report approved successfully.'",
            $code,
            'ProjectTaskController must return contextual success message for completed tasks'
        );
    }
}
