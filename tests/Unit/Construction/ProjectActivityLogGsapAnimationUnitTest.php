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

    public function test_activity_log_component_renders_single_line_listing_layout_with_view_icon(): void
    {
        $componentPath = resource_path('js/Pages/Construction/Components/ProjectActivityLog.jsx');
        $code = file_get_contents($componentPath);

        // 1. Must import and use Eye icon for viewing details
        $this->assertMatchesRegularExpression(
            '/import\s*\{[^}]*Eye[^}]*\}\s*from\s*["\']lucide-react["\']/',
            $code,
            'ProjectActivityLog must import Eye icon from lucide-react'
        );

        // 2. Must render an Eye icon or view details button
        $this->assertStringContainsString('<Eye', $code, 'Activity log row must render the Eye view icon');

        // 3. Must support single-line listing mode with proper spacing
        $this->assertMatchesRegularExpression(
            '/(?:viewMode\s*===\s*["\']list["\']|space-y-[0-9]+)/',
            $code,
            'Activity log must provide a single-line listing layout'
        );
    }
}
