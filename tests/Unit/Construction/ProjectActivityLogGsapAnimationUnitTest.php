<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectActivityLogGsapAnimationUnitTest extends TestCase
{
    public function test_activity_log_component_imports_gsap_and_uses_gsap_animations(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx');
        $this->assertFileExists($componentPath);

        $code = file_get_contents($componentPath);

        // 1. Must import GSAP
        $this->assertStringContainsString("import gsap from \"gsap\"", $code, 'ProjectActivityLog must import GSAP');

        // 2. Must use GSAP animation context / timeline or fromTo for card entrance
        $this->assertMatchesRegularExpression(
            '/gsap\.(?:fromTo|timeline|to|context)/',
            $code,
            'ProjectActivityLog must invoke GSAP animations'
        );
    }

    public function test_activity_log_component_includes_live_blinking_indicators(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx');
        $code = file_get_contents($componentPath);

        // Must include blinking / pulsating live status indicators (animate-ping or animate-pulse)
        $this->assertStringContainsString('animate-ping', $code, 'ProjectActivityLog must include an animated ping beacon for live / recent activities');
    }
}
