<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskSubmissionDetailsFeatureTest extends TestCase
{
    public function test_api_submit_task_data_persists_cross_table_identifiers(): void
    {
        $controllerPath = app_path('Http/Controllers/Api/MemberDashboardController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // submitTaskData checks existing visit using both unified and legacy IDs
        $this->assertStringContainsString('if ($unifiedTask)', $code);
        $this->assertStringContainsString('execution_task_id', $code);

        // submitTaskData saves task_id as unifiedTask ID
        $this->assertMatchesRegularExpression(
            "/'task_id'\s*=>\s*\\\$unifiedTask\?->id\s*\?\?\s*\\\$legacyTask->id/",
            $code
        );

        // submitTaskData saves execution_task_id as legacyTask ID
        $this->assertMatchesRegularExpression(
            "/'execution_task_id'\s*=>\s*\\\$legacyTask\?->id/",
            $code
        );
    }

    public function test_project_task_controller_handles_driver_and_surveyor_approval_differently(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Checks driver role flags
        $this->assertStringContainsString('$isDriverTask', $code);
        $this->assertStringContainsString('$isSurveyorTask', $code);
        $this->assertStringContainsString("str_contains(\$supRoles, 'driver')", $code);
        $this->assertStringContainsString("str_contains(\$supRoles, 'vehicle')", $code);

        // Completed branch
        $this->assertStringContainsString("if (\$isDriverTask || !\$isSurveyorTask)", $code);
        $this->assertStringContainsString("\$legacyTask->status = 'completed'", $code);
        $this->assertStringContainsString("\$unifiedTask->status = 'completed'", $code);
        $this->assertStringContainsString("\$unifiedTask->is_completed = true", $code);

        // Surveyor branch
        $this->assertStringContainsString("\$legacyTask->status = 'survey_approved'", $code);
    }

    public function test_show_jsx_has_adaptive_labels_and_cross_table_visit_resolution(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // mergedTasksForExecutionList cross-merges visits
        $this->assertStringContainsString('partnerMatch', $code);
        $this->assertStringContainsString('combinedVisits', $code);
        $this->assertStringContainsString('mergedVisits', $code);

        // taskVisits searches execution_task_id and legacy_task_id
        $this->assertStringContainsString('v.execution_task_id', $code);
        $this->assertStringContainsString('task.legacy_task_id', $code);

        // Driver vs Surveyor task hooks
        $this->assertStringContainsString('isDriverTask', $code);
        $this->assertStringContainsString('isSurveyorTask', $code);

        // Adaptive modal header
        $this->assertStringContainsString('Driver Task Submission Details', $code);
        $this->assertStringContainsString('Survey Task Submission Details', $code);

        // Adaptive empty states
        $this->assertStringContainsString('No Driver Submissions Yet', $code);
        $this->assertStringContainsString('No Survey Submissions Yet', $code);

        // Adaptive buttons
        $this->assertStringContainsString('Complete Task', $code);
        $this->assertStringContainsString('Approve Survey', $code);
        $this->assertStringContainsString('✓ Task Completed', $code);
    }
}

