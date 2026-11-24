<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class WorkflowTrackerMotionPathUnitTest extends TestCase
{
    public function test_workflow_tracker_registers_and_uses_gsap_motion_path_plugin(): void
    {
        $path = resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('import { MotionPathPlugin } from "gsap/MotionPathPlugin"', $code);
        $this->assertStringContainsString('import { MotionPathHelper } from "gsap/MotionPathHelper"', $code);
        $this->assertStringContainsString('gsap.registerPlugin(MotionPathPlugin, MotionPathHelper)', $code);
        $this->assertStringContainsString('motionPath:', $code);
        $this->assertStringContainsString('alignOrigin: [0.5, 0.5]', $code);
        $this->assertStringContainsString('autoRotate: 0', $code);
        $this->assertStringContainsString('MotionPathHelper.create(', $code);
    }

    public function test_workflow_tracker_renders_aligned_file_icon_instead_of_star(): void
    {
        $path = resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx');
        $code = file_get_contents($path);

        $this->assertStringContainsString('wf-motion-path', $code);
        $this->assertStringContainsString('wf-motion-file', $code, 'WorkflowTracker must use a gliding file icon');
        $this->assertStringNotContainsString('wf-motion-star', $code, 'Star icon must be replaced by the file icon');
        $this->assertStringContainsString('wf-step-circle', $code, 'Step circles must be measured for exact path alignment');
        $this->assertStringContainsString('getBoundingClientRect', $code, 'Path must dynamically align to actual circle coordinates');
        $this->assertStringContainsString('ResizeObserver', $code, 'Path alignment must stay synced on resize');
        $this->assertStringContainsString('#0ae448', $code);
        $this->assertStringContainsString('#abff84', $code);
    }

    public function test_motion_path_effect_is_scoped_only_to_workflow_tracker(): void
    {
        $shellCode = file_get_contents(resource_path('js/Pages/Construction/Components/ConstructionShell.jsx'));
        $statCardCode = file_get_contents(resource_path('js/Pages/Construction/Components/StatCard.jsx'));
        $activityLogCode = file_get_contents(resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx'));

        $this->assertStringNotContainsString('MotionPathPlugin', $shellCode);
        $this->assertStringNotContainsString('MotionPathHelper', $shellCode);
        $this->assertStringNotContainsString('MotionPathPlugin', $statCardCode);
        $this->assertStringNotContainsString('MotionPathHelper', $statCardCode);
        $this->assertStringNotContainsString('MotionPathPlugin', $activityLogCode);
        $this->assertStringNotContainsString('MotionPathHelper', $activityLogCode);
    }

    public function test_workflow_tracker_stops_file_at_active_project_step_and_starts_gsap_bling_animation(): void
    {
        $path = resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx');
        $code = file_get_contents($path);

        // File must travel along MotionPath ending at the active stage progressRatio (not looping endlessly past it)
        $this->assertStringContainsString('start: 0', $code);
        $this->assertStringContainsString('end: progressRatio', $code);
        $this->assertStringContainsString('gsap.timeline(', $code);

        // Once arrived at the active step, proper GSAP bling / blinking animations activate on the current stage
        $this->assertStringContainsString('wf-step-circle-active', $code);
        $this->assertStringContainsString('wf-bling-ring', $code);
        $this->assertStringContainsString('wf-bling-sparkle', $code);
        $this->assertStringContainsString('wf-stage-pill-active', $code);
        $this->assertStringContainsString('wf-file-aura', $code);
        $this->assertStringContainsString('yoyo: true', $code);
    }
}
