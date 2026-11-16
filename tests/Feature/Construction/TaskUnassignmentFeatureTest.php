<?php

namespace Tests\Feature\Construction;

use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskAssignment;
use Tests\TestCase;

class TaskUnassignmentFeatureTest extends TestCase
{
    public function test_react_projects_show_defines_remove_assignment_button_and_confirmation_modal(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $content = file_get_contents($showPath);

        // Check for Remove Assignment button
        $this->assertStringContainsString(
            'Remove Assignment',
            $content,
            'Projects Show.jsx must contain a "Remove Assignment" button'
        );

        // Check for confirmation modal title / text
        $this->assertStringContainsString(
            'Remove Task Assignment',
            $content,
            'Projects Show.jsx must contain a confirmation modal for removing task assignment'
        );

        // Check route call
        $this->assertStringContainsString(
            'projects.tasks-v2.unassign',
            $content,
            'Projects Show.jsx must call the unassign route'
        );
    }

    public function test_unassign_controller_logic_clears_supervisors_and_assignees(): void
    {
        // Verify controller source code handles both legacy and unified task unassignment
        $code = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));

        $this->assertStringContainsString('function unassignTask', $code);
        $this->assertStringContainsString('supervisor_member_id = null', $code);
        $this->assertStringContainsString('assigned_supervisor_member_id = null', $code);
        $this->assertStringContainsString('ExecutionTaskAssignee::where', $code);
        $this->assertStringContainsString('TaskAssignment::where', $code);
    }

    public function test_drafting_jobs_cleanup_does_not_query_non_existent_columns_on_drafting_jobs_table(): void
    {
        $code = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectTaskController.php'));

        // construction_drafting_jobs table does NOT have task_id or execution_task_id columns.
        // It must NOT do DraftingJob::... ->orWhere('task_id' ...) or ->orWhere('execution_task_id' ...)
        $this->assertDoesNotMatchRegularExpression(
            '/DraftingJob::[^;]+(?:where|orWhere)\(\s*[\'"](?:task_id|execution_task_id)[\'"]/i',
            $code,
            'DraftingJob must not be queried directly with task_id or execution_task_id because those columns do not exist on construction_drafting_jobs table'
        );

        // It must cleanly query SurveyVisit and SurveySubmission to resolve drafting jobs
        $this->assertStringContainsString('SurveyVisit::where(\'project_id\', $project->id)', $code);
        $this->assertStringContainsString('SurveySubmission::where(\'project_id\', $project->id)', $code);
    }
}

