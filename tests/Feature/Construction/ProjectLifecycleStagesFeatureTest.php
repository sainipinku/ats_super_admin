<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class ProjectLifecycleStagesFeatureTest extends TestCase
{
    public function test_projects_index_defines_five_stage_flow_without_foundation_or_structure(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // 1. Must mention 5-stage lifecycle in header description
        $this->assertStringContainsString(
            '5-stage lifecycle',
            $code,
            'Projects Index must mention 5-stage lifecycle'
        );

        // 2. Must not contain foundation or structure in projectStatusFlow
        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']foundation["\']/s',
            $code
        );
        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']structure["\']/s',
            $code
        );

        // 3. Must have the 5 stages in proper order
        $this->assertMatchesRegularExpression(
            '/value:\s*["\']planning["\'].*value:\s*["\']survey["\'].*value:\s*["\']finishing["\'].*value:\s*["\']handover["\'].*value:\s*["\']completed["\']/s',
            $code,
            'Stages must follow linear order: planning -> survey -> finishing -> handover -> completed'
        );
    }

    public function test_dashboard_and_controllers_omit_foundation_and_structure_stages(): void
    {
        $superDashPath = app_path('Http/Controllers/SuperAdmin/Construction/DashboardController.php');
        $this->assertFileExists($superDashPath);
        $superCode = file_get_contents($superDashPath);

        $this->assertStringNotContainsString("'foundation'", $superCode);
        $this->assertStringNotContainsString("'structure'", $superCode);

        $adminDashPath = app_path('Http/Controllers/Admin/Construction/DashboardController.php');
        $this->assertFileExists($adminDashPath);
        $adminCode = file_get_contents($adminDashPath);

        $this->assertStringNotContainsString("'foundation'", $adminCode);
        $this->assertStringNotContainsString("'structure'", $adminCode);

        $dashJsxPath = resource_path('js/Pages/SuperAdmin/Construction/Dashboard.jsx');
        $this->assertFileExists($dashJsxPath);
        $dashJsx = file_get_contents($dashJsxPath);

        $this->assertMatchesRegularExpression(
            '/value:\s*["\']planning["\'].*value:\s*["\']survey["\'].*value:\s*["\']finishing["\'].*value:\s*["\']handover["\'].*value:\s*["\']completed["\']/s',
            $dashJsx
        );
    }
}

