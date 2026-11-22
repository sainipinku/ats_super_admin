<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class DashboardDynamicScopingUnitTest extends TestCase
{
    public function test_superadmin_dashboard_controller_scopes_data_dynamically_by_company_and_actor(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/DashboardController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // 1. Controller must resolve company ID dynamically from actor or request
        $this->assertMatchesRegularExpression(
            "/getAccessibleCompanyId/i",
            $code,
            'DashboardController must use getAccessibleCompanyId to resolve company context'
        );

        // 2. Member/Employee counting must be scoped, not raw global Member::count()
        $this->assertStringNotContainsString(
            '$totalEmployees = Member::count();',
            $code,
            'DashboardController must not use unscoped Member::count()'
        );

        // 3. Activity logs must be scoped for actor/company, not raw ConstructionActivityLog::latest()
        $this->assertStringNotContainsString(
            "ConstructionActivityLog::with(['project'])\n                ->latest('created_at')",
            $code,
            'DashboardController must scope recentActivity by company or actor'
        );

        // 4. Must support company_id or id parameter for dynamic filtering
        $this->assertMatchesRegularExpression(
            "/company_id|request\(['\"]company_id['\"]\)|request\(['\"]id['\"]\)/i",
            $code,
            'DashboardController must support dynamic company filter parameter'
        );
    }

    public function test_admin_dashboard_controller_scopes_data_dynamically(): void
    {
        $adminControllerPath = app_path('Http/Controllers/Admin/Construction/DashboardController.php');
        $this->assertFileExists($adminControllerPath);

        $code = file_get_contents($adminControllerPath);

        // Member/Employee counting must not be raw Member::count()
        $this->assertStringNotContainsString(
            '$totalEmployees = Member::count();',
            $code,
            'Admin DashboardController must not use unscoped Member::count()'
        );
    }
}
