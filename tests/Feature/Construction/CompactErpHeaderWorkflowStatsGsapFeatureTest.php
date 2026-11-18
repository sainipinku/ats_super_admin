<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class CompactErpHeaderWorkflowStatsGsapFeatureTest extends TestCase
{
    public function test_superadmin_and_admin_project_show_use_compact_stat_cards_with_gsap_stagger(): void
    {
        $superShow = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $adminShow = file_get_contents(resource_path('js/Pages/Admin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('import gsap from "gsap"', $superShow, 'SuperAdmin Projects/Show.jsx must import GSAP for metric card stagger');
        $this->assertStringContainsString('<StatCard compact', $superShow, 'SuperAdmin Projects/Show.jsx must render StatCard in compact mode');
        $this->assertStringContainsString('gap-2.5', $superShow, 'SuperAdmin Projects/Show.jsx metrics grid must use tight spacing');
        $this->assertStringContainsString('.stat-card-item', $superShow, 'SuperAdmin Projects/Show.jsx must animate .stat-card-item elements with GSAP');

        $this->assertStringContainsString('import gsap from "gsap"', $adminShow, 'Admin Projects/Show.jsx must import GSAP for metric card stagger');
        $this->assertStringContainsString('<StatCard compact', $adminShow, 'Admin Projects/Show.jsx must render StatCard in compact mode');
        $this->assertStringContainsString('gap-2.5', $adminShow, 'Admin Projects/Show.jsx metrics grid must use tight spacing');
        $this->assertStringContainsString('.stat-card-item', $adminShow, 'Admin Projects/Show.jsx must animate .stat-card-item elements with GSAP');
    }

    public function test_all_compact_erp_top_components_clean_up_gsap_contexts(): void
    {
        $components = [
            resource_path('js/Pages/Construction/Components/ConstructionShell.jsx'),
            resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx'),
            resource_path('js/Pages/Construction/Components/StatCard.jsx'),
        ];

        foreach ($components as $componentPath) {
            $code = file_get_contents($componentPath);
            $this->assertStringContainsString('gsap.context(', $code, basename($componentPath) . ' must scope animations with gsap.context');
            $this->assertStringContainsString('ctx.revert()', $code, basename($componentPath) . ' must revert gsap context on unmount');
        }
    }
}
