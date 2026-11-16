<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class DashboardFinanceOverviewRemovalFeatureTest extends TestCase
{
    public function test_dashboard_section_order_flows_directly_from_fleet_to_attendance(): void
    {
        $dashboardPath = resource_path('js/Pages/SuperAdmin/Construction/Dashboard.jsx');
        $this->assertFileExists($dashboardPath);

        $code = file_get_contents($dashboardPath);

        $fleetPos = strpos($code, 'title="Fleet, Equipment & Clients"');
        $attendancePos = strpos($code, 'title="Attendance Summary"');

        $this->assertNotFalse($fleetPos, 'Dashboard must have Fleet, Equipment & Clients section');
        $this->assertNotFalse($attendancePos, 'Dashboard must have Attendance Summary section');
        $this->assertLessThan($attendancePos, $fleetPos, 'Fleet section must precede Attendance section');

        // Between Fleet section and Attendance section, there must be NO Finance Overview
        $between = substr($code, $fleetPos, $attendancePos - $fleetPos);
        $this->assertStringNotContainsString('Finance Overview', $between);
        $this->assertStringNotContainsString('Monthly Revenue', $between);
        $this->assertStringNotContainsString('Monthly Expenses', $between);
        $this->assertStringNotContainsString('Monthly Invoiced', $between);
    }

    public function test_controllers_do_not_execute_redundant_finance_aggregations(): void
    {
        $superController = app_path('Http/Controllers/SuperAdmin/Construction/DashboardController.php');
        $adminController = app_path('Http/Controllers/Admin/Construction/DashboardController.php');

        $superCode = file_get_contents($superController);
        $adminCode = file_get_contents($adminController);

        $this->assertStringNotContainsString('$monthlyExpenses', $superCode);
        $this->assertStringNotContainsString('$monthlyExpenses', $adminCode);

        $this->assertStringNotContainsString("'monthlyRevenue'", $superCode);
        $this->assertStringNotContainsString("'monthlyRevenue'", $adminCode);
    }
}

