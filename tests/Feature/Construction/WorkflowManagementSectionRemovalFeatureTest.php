<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class WorkflowManagementSectionRemovalFeatureTest extends TestCase
{
    public function test_superadmin_project_show_preserves_core_sections_after_workflow_panel_removal(): void
    {
        $path = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $content = file_get_contents($path);

        // Removed sections & unused panel imports
        $this->assertStringNotContainsString('5-Stage Complete Workflow Management', $content);
        $this->assertStringNotContainsString('Drafting Jobs & CAD Submissions', $content);
        $this->assertStringNotContainsString('title="Drawing Approvals"', $content);
        $this->assertStringNotContainsString('SupervisorSingleScreenReview', $content);
        $this->assertStringNotContainsString('DraftingWorkflowPanel', $content);
        $this->assertStringNotContainsString('AccountsVerificationPanel', $content);
        $this->assertStringNotContainsString('SurveySubmissionPanel', $content);
        $this->assertStringNotContainsString('WorkflowAuditTrail', $content);

        // Core sections preserved
        $this->assertStringContainsString('<WorkflowTracker', $content);
        $this->assertStringContainsString('title="Scope of Work"', $content);
        $this->assertStringContainsString('title="Survey Submission Queue"', $content);
        $this->assertStringContainsString('<DynamicChecklistManager', $content);
        $this->assertStringContainsString('<ProjectActivityLog', $content);
    }

    public function test_admin_project_show_preserves_core_sections_after_workflow_panel_removal(): void
    {
        $path = resource_path('js/Pages/Admin/Construction/Projects/Show.jsx');
        $content = file_get_contents($path);

        // Removed sections & unused panel imports
        $this->assertStringNotContainsString('5-Stage Complete Workflow Management', $content);
        $this->assertStringNotContainsString('title="Drafting & Approval"', $content);
        $this->assertStringNotContainsString('SupervisorSingleScreenReview', $content);
        $this->assertStringNotContainsString('DraftingWorkflowPanel', $content);
        $this->assertStringNotContainsString('AccountsVerificationPanel', $content);
        $this->assertStringNotContainsString('SurveySubmissionPanel', $content);
        $this->assertStringNotContainsString('WorkflowAuditTrail', $content);

        // Core sections preserved
        $this->assertStringContainsString('<WorkflowTracker', $content);
        $this->assertStringContainsString('Execution Tasks', $content);
        $this->assertStringContainsString('<DynamicChecklistManager', $content);
        $this->assertStringContainsString('<ProjectActivityLog', $content);
    }
}
