<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ChecklistMonitoringDashboardGsapUnitTest extends TestCase
{
    public function test_projects_index_implements_gsap_in_checklist_monitoring_dashboard(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $content = file_get_contents($indexPath);

        // GSAP must be imported
        $this->assertMatchesRegularExpression(
            '/import\s+gsap\s+from\s+["\']gsap["\']/',
            $content,
            'Projects Index.jsx must import gsap'
        );

        // Scoped context cleanup must be implemented
        $this->assertStringContainsString('gsap.context', $content, 'Projects Index.jsx must use gsap.context');
        $this->assertStringContainsString('ctx.revert()', $content, 'Projects Index.jsx must clean up gsap.context on unmount');

        // Checklist Monitoring Dashboard must use deep-link with focus=checklist or dynamic-checklist-manager anchor
        $this->assertMatchesRegularExpression(
            '/focus=checklist|#dynamic-checklist-manager/',
            $content,
            'Checklist Monitoring Dashboard cards must deep-link to project show with focus=checklist or #dynamic-checklist-manager'
        );
    }

    public function test_dynamic_checklist_manager_provides_anchor_and_auto_scroll(): void
    {
        $managerPath = resource_path('js/Pages/Construction/Components/DynamicChecklistManager.jsx');
        $this->assertFileExists($managerPath);

        $content = file_get_contents($managerPath);

        // Container must have id for dynamic-checklist-manager
        $this->assertStringContainsString(
            'id="dynamic-checklist-manager"',
            $content,
            'DynamicChecklistManager must expose id="dynamic-checklist-manager" for deep linking'
        );

        // Must implement scrollIntoView and GSAP highlight effect
        $this->assertStringContainsString('scrollIntoView', $content, 'DynamicChecklistManager must implement smooth scrollIntoView');
        $this->assertStringContainsString('focus', $content, 'DynamicChecklistManager must inspect focus param or hash');
    }
}
