<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class WorkflowTrackerMotionPathFeatureTest extends TestCase
{
    public function test_project_details_pages_mount_workflow_tracker_with_isolated_motion_path(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('<WorkflowTracker', $superShow);
        $this->assertStringContainsString('<WorkflowTracker', $adminShow);

        // MotionPath plugin must stay encapsulated inside WorkflowTracker (Complete Workflow Progress)
        $this->assertStringNotContainsString('MotionPathPlugin', $superShow);
        $this->assertStringNotContainsString('MotionPathPlugin', $adminShow);
    }

    public function test_workflow_tracker_cleans_up_gsap_context_and_motion_path_helper(): void
    {
        $trackerCode = file_get_contents(resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'));

        $this->assertStringContainsString('gsap.context(', $trackerCode);
        $this->assertStringContainsString('ctx.revert()', $trackerCode);
        $this->assertStringContainsString('.kill()', $trackerCode);
        $this->assertStringContainsString('Complete Workflow Progress', $trackerCode);
    }
}
