<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class CompactErpHeaderWorkflowStatsGsapUnitTest extends TestCase
{
    public function test_construction_shell_is_compact_and_uses_gsap_animations(): void
    {
        $path = resource_path('js/Pages/Construction/Components/ConstructionShell.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('import gsap from "gsap"', $code, 'ConstructionShell must import GSAP');
        $this->assertMatchesRegularExpression('/gsap\.(?:context|fromTo|to)/', $code, 'ConstructionShell must invoke GSAP animations');
        $this->assertStringContainsString('ctx.revert()', $code, 'ConstructionShell must clean up GSAP context');
        $this->assertStringContainsString('shell-nav-pill', $code, 'ConstructionShell nav pills must have GSAP target class');
        $this->assertStringNotContainsString('rounded-3xl border border-slate-200 bg-white p-6', $code, 'ConstructionShell header must use compact spacing instead of bulky p-6');
    }

    public function test_workflow_tracker_is_compact_and_uses_gsap_animations(): void
    {
        $path = resource_path('js/Pages/Construction/Components/WorkflowTracker.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('import gsap from "gsap"', $code, 'WorkflowTracker must import GSAP');
        $this->assertMatchesRegularExpression('/gsap\.(?:context|fromTo|to)/', $code, 'WorkflowTracker must invoke GSAP animations');
        $this->assertStringContainsString('ctx.revert()', $code, 'WorkflowTracker must clean up GSAP context');
        $this->assertStringContainsString('wf-step-node', $code, 'WorkflowTracker step nodes must have GSAP target class');
        $this->assertStringContainsString('wf-progress-bar', $code, 'WorkflowTracker progress bar must have GSAP target class');
        $this->assertStringNotContainsString('w-12 h-12', $code, 'WorkflowTracker nodes must be compact instead of w-12 h-12');
    }

    public function test_stat_card_supports_compact_mode_and_uses_gsap_animations(): void
    {
        $path = resource_path('js/Pages/Construction/Components/StatCard.jsx');
        $this->assertFileExists($path);

        $code = file_get_contents($path);

        $this->assertStringContainsString('import gsap from "gsap"', $code, 'StatCard must import GSAP');
        $this->assertMatchesRegularExpression('/gsap\.(?:context|fromTo|to)/', $code, 'StatCard must invoke GSAP animations');
        $this->assertStringContainsString('compact', $code, 'StatCard must support compact mode prop');
        $this->assertStringContainsString('stat-card-item', $code, 'StatCard must expose stat-card-item class for GSAP staggered grids');
    }
}
