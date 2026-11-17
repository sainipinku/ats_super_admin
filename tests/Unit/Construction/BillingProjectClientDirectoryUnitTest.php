<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class BillingProjectClientDirectoryUnitTest extends TestCase
{
    public function test_billing_controller_and_service_prepare_projects_with_client_tasks_and_khasra(): void
    {
        $superController = file_get_contents(base_path('app/Http/Controllers/SuperAdmin/Construction/BillingController.php'));
        $adminController = file_get_contents(base_path('app/Http/Controllers/Admin/Construction/BillingController.php'));
        $billingService = file_get_contents(base_path('app/Services/Construction/ConstructionBillingService.php'));

        $this->assertStringContainsString('getProjectsBillingDirectory', $superController, 'SuperAdmin BillingController must call getProjectsBillingDirectory');
        $this->assertStringContainsString('getProjectsBillingDirectory', $adminController, 'Admin BillingController must call getProjectsBillingDirectory');
        $this->assertStringContainsString('exportCsv', $superController, 'SuperAdmin BillingController must have exportCsv');
        $this->assertStringContainsString('exportCsv', $adminController, 'Admin BillingController must have exportCsv');

        $this->assertStringContainsString('khasra_number', $billingService, 'ConstructionBillingService must include khasra_number');
        $this->assertStringContainsString('task_counts', $billingService, 'ConstructionBillingService must provide task_counts');
        $this->assertStringContainsString('checklist_counts', $billingService, 'ConstructionBillingService must provide checklist_counts');
        $this->assertStringContainsString('exportProjectsBillingCsv', $billingService, 'ConstructionBillingService must provide exportProjectsBillingCsv');
    }

    public function test_billing_workspace_jsx_contains_project_client_listing_modal_and_csv_download(): void
    {
        $workspace = file_get_contents(base_path('resources/js/Pages/Construction/Billing/BillingWorkspace.jsx'));

        $this->assertStringContainsString('Download CSV', $workspace, 'BillingWorkspace must include a Download CSV button');
        $this->assertStringContainsString('View Details', $workspace, 'BillingWorkspace must include a View Details button');
        $this->assertStringContainsString('khasra_number', $workspace, 'BillingWorkspace must reference khasra_number');
        $this->assertStringContainsString('task_counts', $workspace, 'BillingWorkspace must render task counts');
        $this->assertStringContainsString('checklist_counts', $workspace, 'BillingWorkspace must render checklist counts');
    }
}
