<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class BillingReportDateRangeDownloadUnitTest extends TestCase
{
    public function test_billing_workspace_jsx_has_date_range_download_modal_and_filtering(): void
    {
        $jsxPath = base_path('resources/js/Pages/Construction/Billing/BillingWorkspace.jsx');
        $this->assertFileExists($jsxPath);
        $content = file_get_contents($jsxPath);

        // 1. Must have download modal state and trigger
        $this->assertMatchesRegularExpression('/isDownloadModalOpen|showDownloadModal|downloadModal/i', $content, 'Must have download modal state in BillingWorkspace');

        // 2. Must have Date Range inputs/selectors (From Date / Start Date, To Date / End Date)
        $this->assertMatchesRegularExpression('/start_?date|from_?date|From Date/i', $content, 'Must have From/Start Date in modal');
        $this->assertMatchesRegularExpression('/end_?date|to_?date|To Date/i', $content, 'Must have To/End Date in modal');

        // 3. Must have quick date presets (e.g. This Month, Today, Last 30 Days)
        $this->assertStringContainsString('This Month', $content, 'Must have This Month date preset');
        $this->assertStringContainsString('Today', $content, 'Must have Today date preset');

        // 4. Must filter export data strictly by selected date range before generating sheet
        $this->assertMatchesRegularExpression('/filter.*date|date.*filter/i', $content, 'Must filter data by date range');
    }

    public function test_billing_service_and_controllers_support_date_range_filtered_export(): void
    {
        $servicePath = base_path('app/Services/Construction/ConstructionBillingService.php');
        $superCtrlPath = base_path('app/Http/Controllers/SuperAdmin/Construction/BillingController.php');
        $adminCtrlPath = base_path('app/Http/Controllers/Admin/Construction/BillingController.php');

        $this->assertFileExists($servicePath);
        $this->assertFileExists($superCtrlPath);
        $this->assertFileExists($adminCtrlPath);

        $serviceContent = file_get_contents($servicePath);
        $superContent = file_get_contents($superCtrlPath);
        $adminContent = file_get_contents($adminCtrlPath);

        // Service returns created_at for date filtering
        $this->assertStringContainsString("'created_at'", $serviceContent, 'ConstructionBillingService directory must include created_at');

        // Controllers exportCsv must accept Request to filter by start_date/end_date
        $this->assertMatchesRegularExpression('/exportCsv\s*\(\s*Request\s+\$request/i', $superContent, 'SuperAdmin BillingController exportCsv must accept Request');
        $this->assertMatchesRegularExpression('/exportCsv\s*\(\s*Request\s+\$request/i', $adminContent, 'Admin BillingController exportCsv must accept Request');
    }

    public function test_task_and_checklist_fields_are_excluded_from_csv_exports(): void
    {
        $jsxPath = base_path('resources/js/Pages/Construction/Billing/BillingWorkspace.jsx');
        $servicePath = base_path('app/Services/Construction/ConstructionBillingService.php');

        $jsxContent = file_get_contents($jsxPath);
        $serviceContent = file_get_contents($servicePath);

        // Neither frontend export handler nor backend service should have task/checklist columns in CSV headers
        $this->assertStringNotContainsString('"Total Tasks"', $jsxContent, 'BillingWorkspace must not include Total Tasks in CSV');
        $this->assertStringNotContainsString('"Completed Tasks"', $jsxContent, 'BillingWorkspace must not include Completed Tasks in CSV');
        $this->assertStringNotContainsString('"Task Progress %"', $jsxContent, 'BillingWorkspace must not include Task Progress % in CSV');
        $this->assertStringNotContainsString('"Total Checklists"', $jsxContent, 'BillingWorkspace must not include Total Checklists in CSV');
        $this->assertStringNotContainsString('"Checklist Progress %"', $jsxContent, 'BillingWorkspace must not include Checklist Progress % in CSV');

        $this->assertStringNotContainsString("'Total Tasks'", $serviceContent, 'ConstructionBillingService must not include Total Tasks in CSV');
        $this->assertStringNotContainsString("'Total Checklists'", $serviceContent, 'ConstructionBillingService must not include Total Checklists in CSV');
    }
}
