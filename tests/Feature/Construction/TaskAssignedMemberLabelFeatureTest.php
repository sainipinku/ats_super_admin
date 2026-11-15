<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskAssignedMemberLabelFeatureTest extends TestCase
{
    public function test_projects_show_replaces_supervisor_with_assigned_member_in_task_row(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $content = file_get_contents($showPath);

        // The task card column header should use Assigned Member instead of Supervisor
        $this->assertDoesNotMatchRegularExpression(
            '/isDraftingAssigned\s*\?\s*["\']Draft Person["\']\s*:\s*["\']Supervisor["\']/',
            $content,
            'Projects Show.jsx task card header must not say Supervisor'
        );

        $this->assertMatchesRegularExpression(
            '/isDraftingAssigned\s*\?\s*["\']Draft Person["\']\s*:\s*["\']Assigned Member["\']/',
            $content,
            'Projects Show.jsx task card header must use Assigned Member'
        );
    }

    public function test_projects_show_replaces_supervisor_label_in_overview_and_modal(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $content = file_get_contents($showPath);

        // Overview block
        $this->assertDoesNotMatchRegularExpression(
            '/<p[^>]*>Supervisor<\/p>\s*<p[^>]*>\{task\.supervisor\?\.name/',
            $content,
            'Overview block should not use Supervisor label'
        );
        $this->assertMatchesRegularExpression(
            '/<p[^>]*>Assigned Member<\/p>\s*<p[^>]*>\{task\.supervisor\?\.name/',
            $content,
            'Overview block should use Assigned Member label'
        );

        // Transfer modal
        $this->assertDoesNotMatchRegularExpression(
            '/Current Supervisor \/ Assignee:/',
            $content,
            'Transfer modal must not say Current Supervisor / Assignee:'
        );
        $this->assertMatchesRegularExpression(
            '/Current Assigned Member:/',
            $content,
            'Transfer modal must say Current Assigned Member:'
        );
    }

    public function test_execution_workspace_replaces_supervisor_with_assigned_member(): void
    {
        $workspacePath = resource_path('js/Pages/Construction/Execution/ExecutionWorkspace.jsx');
        $this->assertFileExists($workspacePath);

        $content = file_get_contents($workspacePath);

        $this->assertDoesNotMatchRegularExpression(
            '/<MiniMetric\s+label=["\']Supervisor["\']/',
            $content,
            'ExecutionWorkspace must not have MiniMetric with label Supervisor'
        );
        $this->assertMatchesRegularExpression(
            '/<MiniMetric\s+label=["\']Assigned Member["\']/',
            $content,
            'ExecutionWorkspace must have MiniMetric with label Assigned Member'
        );
    }
}

