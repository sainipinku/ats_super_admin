<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ChecklistMonitoringCompactGsapUnitTest extends TestCase
{
    private string $indexPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
    }

    public function test_superadmin_projects_index_implements_compact_mini_space_checklist_monitoring(): void
    {
        $this->assertFileExists($this->indexPath);
        $content = file_get_contents($this->indexPath);

        // ChecklistMonitoringDashboard exists
        $this->assertStringContainsString('function ChecklistMonitoringDashboard(', $content);

        // Compact mini-space styling markers
        $this->assertStringContainsString('cmd-stage', $content, 'Must have cmd-stage container for mini-space styling');
        $this->assertStringContainsString('cmd-card', $content, 'Must have cmd-card classes');
        $this->assertStringContainsString('cmd-progress-bar', $content, 'Must have cmd-progress-bar');
        
        // Compact mini space padding (p-3 or p-2.5 sm:p-3, not bulky p-6/p-5)
        $this->assertMatchesRegularExpression(
            '/p-2\.5|p-3/',
            $content,
            'Cards must use compact mini-space padding'
        );
    }

    public function test_superadmin_projects_index_implements_proper_gsap_effects(): void
    {
        $content = file_get_contents($this->indexPath);

        // GSAP import & context lifecycle
        $this->assertStringContainsString('import gsap from "gsap";', $content);
        $this->assertStringContainsString('gsap.context(', $content);
        $this->assertStringContainsString('ctx.revert()', $content);

        // Kinetic text reveal
        $this->assertStringContainsString('cmd-title-word', $content, 'Must implement kinetic title word reveal');

        // Springy hover popping effect
        $this->assertStringContainsString('handleCardEnter', $content);
        $this->assertStringContainsString('handleCardLeave', $content);
        $this->assertMatchesRegularExpression('/back\.out\(/', $content, 'Hover pop must use springy back.out easing');

        // Screen zoom-out / smooth stage reveal
        $this->assertMatchesRegularExpression('/scale:\s*1\.0[2-4]/', $content, 'Must use screen zoom-out stage scale effect');

        // Animated progress fill
        $this->assertStringContainsString('data-pct', $content);
    }
}
