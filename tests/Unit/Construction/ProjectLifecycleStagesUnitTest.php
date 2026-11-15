<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectLifecycleStagesUnitTest extends TestCase
{
    public function test_projects_index_lifecycle_stages_exclude_foundation_and_structure_and_order_correctly(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // Must not contain foundation or structure in projectStatusFlow
        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']foundation["\']/s',
            $code,
            'Index.jsx must not have foundation in projectStatusFlow'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']structure["\']/s',
            $code,
            'Index.jsx must not have structure in projectStatusFlow'
        );

        // Planning must be first in projectStatusFlow
        $this->assertMatchesRegularExpression(
            '/projectStatusFlow\s*=\s*\[\s*\{\s*value:\s*["\']planning["\']/',
            $code,
            'Index.jsx must place planning as the first lifecycle stage'
        );
    }

    public function test_dashboard_lifecycle_stages_exclude_foundation_and_structure_and_order_correctly(): void
    {
        $dashPath = resource_path('js/Pages/SuperAdmin/Construction/Dashboard.jsx');
        $this->assertFileExists($dashPath);

        $code = file_get_contents($dashPath);

        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']foundation["\']/s',
            $code,
            'Dashboard.jsx must not have foundation in projectStatusFlow'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/projectStatusFlow\s*=\s*\[[^\]]*value:\s*["\']structure["\']/s',
            $code,
            'Dashboard.jsx must not have structure in projectStatusFlow'
        );

        // Planning must be first in projectStatusFlow
        $this->assertMatchesRegularExpression(
            '/projectStatusFlow\s*=\s*\[\s*\{\s*value:\s*["\']planning["\']/',
            $code,
            'Dashboard.jsx must place planning as the first lifecycle stage'
        );
    }

    public function test_dashboard_controllers_exclude_foundation_and_structure(): void
    {
        $superDashPath = app_path('Http/Controllers/SuperAdmin/Construction/DashboardController.php');
        $this->assertFileExists($superDashPath);
        $superCode = file_get_contents($superDashPath);

        $this->assertStringNotContainsString(
            "'foundation'",
            $superCode,
            'SuperAdmin DashboardController must not contain foundation stage'
        );
        $this->assertStringNotContainsString(
            "'structure'",
            $superCode,
            'SuperAdmin DashboardController must not contain structure stage'
        );

        $adminDashPath = app_path('Http/Controllers/Admin/Construction/DashboardController.php');
        $this->assertFileExists($adminDashPath);
        $adminCode = file_get_contents($adminDashPath);

        $this->assertStringNotContainsString(
            "'foundation'",
            $adminCode,
            'Admin DashboardController must not contain foundation stage'
        );
        $this->assertStringNotContainsString(
            "'structure'",
            $adminCode,
            'Admin DashboardController must not contain structure stage'
        );
    }
}

