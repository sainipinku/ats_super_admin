<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class DashboardFinanceOverviewRemovalUnitTest extends TestCase
{
    public function test_superadmin_construction_dashboard_component_does_not_render_finance_overview(): void
    {
        $dashboardPath = resource_path('js/Pages/SuperAdmin/Construction/Dashboard.jsx');
        $this->assertFileExists($dashboardPath);

        $code = file_get_contents($dashboardPath);

        // Must not contain Finance Overview or its stat card labels
        $this->assertStringNotContainsString('Finance Overview', $code);
        $this->assertStringNotContainsString('Monthly Revenue', $code);
        $this->assertStringNotContainsString('Revenue (All Time)', $code);
        $this->assertStringNotContainsString('Monthly Invoiced', $code);
        $this->assertStringNotContainsString('Monthly Expenses', $code);
    }

    public function test_superadmin_dashboard_controller_omits_finance_stats(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/DashboardController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Controller must not pass 'finance' key in stats array
        $this->assertDoesNotMatchRegularExpression(
            "/'finance'\s*=>/",
            $code,
            'SuperAdmin DashboardController must not include finance stats in dashboard payload'
        );
    }

    public function test_admin_dashboard_controller_omits_finance_stats(): void
    {
        $controllerPath = app_path('Http/Controllers/Admin/Construction/DashboardController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Controller must not pass 'finance' key in stats array
        $this->assertDoesNotMatchRegularExpression(
            "/'finance'\s*=>/",
            $code,
            'Admin DashboardController must not include finance stats in dashboard payload'
        );
    }
}

