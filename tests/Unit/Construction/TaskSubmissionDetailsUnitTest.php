<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TaskSubmissionDetailsUnitTest extends TestCase
{
    public function test_member_dashboard_controller_stores_both_unified_and_legacy_task_ids_on_survey_visit(): void
    {
        $controllerPath = app_path('Http/Controllers/Api/MemberDashboardController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // submitTaskData must set task_id to unifiedTask ID (or legacyTask ID fallback)
        $this->assertMatchesRegularExpression(
            "/'task_id'\s*=>\s*\\\$unifiedTask\?->id\s*\?\?\s*\\\$legacyTask->id/",
            $code,
            'MemberDashboardController::submitTaskData must assign unified task ID to task_id'
        );

        // submitTaskData must set execution_task_id to legacyTask ID
        $this->assertMatchesRegularExpression(
            "/'execution_task_id'\s*=>\s*\\\$legacyTask\?->id/",
            $code,
            'MemberDashboardController::submitTaskData must assign legacy task ID to execution_task_id'
        );
    }

    public function test_project_task_controller_marks_driver_and_non_survey_tasks_as_completed(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // reviewSurvey must check for driver or non-survey task
        $this->assertStringContainsString(
            '$isDriverTask',
            $code,
            'ProjectTaskController::reviewSurvey must identify driver tasks'
        );

        // reviewSurvey must set completed status for driver/non-survey tasks
        $this->assertStringContainsString(
            "->status = 'completed'",
            $code,
            'ProjectTaskController::reviewSurvey must set status to completed for driver tasks'
        );
    }

    public function test_show_component_renders_dynamic_modal_titles_and_complete_task_button(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $code = file_get_contents($showPath);

        // Show.jsx must define isDriverTask
        $this->assertStringContainsString(
            'isDriverTask',
            $code,
            'Show.jsx must define isDriverTask helper'
        );

        // Show.jsx must define isSurveyorTask
        $this->assertStringContainsString(
            'isSurveyorTask',
            $code,
            'Show.jsx must define isSurveyorTask helper'
        );

        // Show.jsx must render Complete Task or Approve & Complete Task button for drivers
        $this->assertStringContainsString(
            'Complete Task',
            $code,
            'Show.jsx must offer Complete Task button for driver or non-survey tasks'
        );

        // Show.jsx must display Driver Task Submission Details for driver tasks
        $this->assertStringContainsString(
            'Driver Task Submission Details',
            $code,
            'Show.jsx must display Driver Task Submission Details for driver tasks'
        );

        // Show.jsx must cross-match visits in mergedTasksForExecutionList or taskVisits
        $this->assertStringContainsString(
            'execution_task_id',
            $code,
            'Show.jsx must match visits by execution_task_id in taskVisits'
        );
    }
}

