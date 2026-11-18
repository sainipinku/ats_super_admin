<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class WorkflowManagementSectionRemovalUnitTest extends TestCase
{
    public function test_superadmin_project_show_does_not_render_five_stage_workflow_management_section(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            '5-Stage Complete Workflow Management',
            $content,
            'SuperAdmin Projects/Show.jsx must not contain the 5-Stage Complete Workflow Management section.'
        );
        $this->assertStringNotContainsString('<SupervisorSingleScreenReview', $content);
        $this->assertStringNotContainsString('<SurveySubmissionPanel', $content);
        $this->assertStringNotContainsString('<DraftingWorkflowPanel', $content);
        $this->assertStringNotContainsString('<AccountsVerificationPanel', $content);
        $this->assertStringNotContainsString('<WorkflowAuditTrail', $content);
        $this->assertStringNotContainsString('activeWorkflowTab', $content);
    }

    public function test_admin_project_show_does_not_contain_five_stage_workflow_management_section(): void
    {
        $path = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            '5-Stage Complete Workflow Management',
            $content,
            'Admin Projects/Show.jsx must not contain the 5-Stage Complete Workflow Management section.'
        );
        $this->assertStringNotContainsString('<SupervisorSingleScreenReview', $content);
        $this->assertStringNotContainsString('<SurveySubmissionPanel', $content);
        $this->assertStringNotContainsString('<DraftingWorkflowPanel', $content);
        $this->assertStringNotContainsString('<AccountsVerificationPanel', $content);
        $this->assertStringNotContainsString('<WorkflowAuditTrail', $content);
        $this->assertStringNotContainsString('activeWorkflowTab', $content);
    }

    public function test_superadmin_project_show_does_not_render_drafting_jobs_or_drawing_approvals_sections(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            'Drafting Jobs & CAD Submissions',
            $content,
            'SuperAdmin Projects/Show.jsx must not contain Drafting Jobs & CAD Submissions section.'
        );
        $this->assertStringNotContainsString(
            'title="Drawing Approvals"',
            $content,
            'SuperAdmin Projects/Show.jsx must not contain Drawing Approvals section.'
        );
        $this->assertStringNotContainsString('id="drafting-jobs-section"', $content);
        $this->assertStringNotContainsString('showDirectDraftUpload', $content);
        $this->assertStringNotContainsString('directDraftForm', $content);
    }

    public function test_admin_project_show_does_not_contain_drafting_and_approval_section(): void
    {
        $path = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $this->assertFileExists($path);

        $content = file_get_contents($path);

        $this->assertStringNotContainsString(
            'title="Drafting & Approval"',
            $content,
            'Admin Projects/Show.jsx must not contain Drafting & Approval section.'
        );
    }
}
