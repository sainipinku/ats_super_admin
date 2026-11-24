<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\SuperAdmin\Construction\BillingController as SuperBillingController;
use App\Http\Controllers\Admin\Construction\BillingController as AdminBillingController;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class BillingProjectClientDirectoryFeatureTest extends TestCase
{
    public function test_routes_for_billing_export_csv_are_registered_for_superadmin_and_admin(): void
    {
        $superExportRoute = Route::getRoutes()->getByName('super.construction.billing.export-csv');
        $adminExportRoute = Route::getRoutes()->getByName('admin.construction.billing.export-csv');

        $this->assertNotNull($superExportRoute, 'Route super.construction.billing.export-csv must be registered');
        $this->assertNotNull($adminExportRoute, 'Route admin.construction.billing.export-csv must be registered');

        $this->assertSame('super/construction/billing/export-csv', $superExportRoute->uri());
        $this->assertSame('admin/construction/billing/export-csv', $adminExportRoute->uri());

        $this->assertContains('construction.permission:billing_invoice.manage,billing_payment.manage', $superExportRoute->middleware());
        $this->assertContains('construction.permission:billing_invoice.manage,billing_payment.manage', $adminExportRoute->middleware());
    }

    public function test_billing_service_export_projects_billing_csv_streams_correct_csv_headers_and_data(): void
    {
        $activityService = $this->createMock(ConstructionActivityService::class);
        $billingService = new ConstructionBillingService($activityService);

        $mockData = [
            [
                'project_code' => 'PRJ-101',
                'name' => 'Metro Station Line 3',
                'current_stage' => 'execution',
                'status' => 'active',
                'priority' => 'high',
                'client' => [
                    'client_code' => 'CLI-101',
                    'name' => 'Urban Transport Corp',
                    'company_name' => 'UTC Corp',
                    'contact_person' => 'Rajesh Verma',
                    'phone' => '9876543210',
                    'email' => 'rajesh@utc.gov.in',
                ],
                'khasra_number' => 'KH-452/98',
                'site_area' => '15000 sq ft',
                'task_counts' => [
                    'total' => 12,
                    'completed' => 8,
                    'in_progress' => 3,
                    'pending' => 1,
                ],
                'checklist_counts' => [
                    'total' => 20,
                    'completed' => 15,
                ],
                'billing_summary' => [
                    'total_billed' => 1250000.0,
                    'total_paid' => 750000.0,
                    'outstanding' => 500000.0,
                ],
            ],
        ];

        $response = $billingService->exportProjectsBillingCsv($mockData);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment; filename="projects_clients_billing_summary_', (string) $response->headers->get('Content-Disposition'));

        ob_start();
        $response->sendContent();
        $csvContent = (string) ob_get_clean();

        $this->assertStringContainsString('Project Code', $csvContent);
        $this->assertStringContainsString('Client Code', $csvContent);
        $this->assertStringContainsString('Khasra Number', $csvContent);
        $this->assertStringNotContainsString('Total Tasks', $csvContent);
        $this->assertStringNotContainsString('Completed Tasks', $csvContent);
        $this->assertStringNotContainsString('In Progress Tasks', $csvContent);
        $this->assertStringNotContainsString('Pending Tasks', $csvContent);
        $this->assertStringNotContainsString('Total Checklists', $csvContent);
        $this->assertStringNotContainsString('Completed Checklists', $csvContent);

        $this->assertStringContainsString('PRJ-101', $csvContent);
        $this->assertStringContainsString('Metro Station Line 3', $csvContent);
        $this->assertStringContainsString('Urban Transport Corp', $csvContent);
        $this->assertStringContainsString('KH-452/98', $csvContent);
        $this->assertStringContainsString('1250000', $csvContent);
        $this->assertStringContainsString('500000', $csvContent);
    }

    public function test_controllers_invoke_billing_service_to_enrich_projects_and_export_csv(): void
    {
        $superRef = new \ReflectionClass(SuperBillingController::class);
        $this->assertTrue($superRef->hasMethod('index'));
        $this->assertTrue($superRef->hasMethod('exportCsv'));

        $superExport = $superRef->getMethod('exportCsv');
        $this->assertSame(\Symfony\Component\HttpFoundation\StreamedResponse::class, $superExport->getReturnType()?->getName());

        $adminRef = new \ReflectionClass(AdminBillingController::class);
        $this->assertTrue($adminRef->hasMethod('index'));
        $this->assertTrue($adminRef->hasMethod('exportCsv'));

        $adminExport = $adminRef->getMethod('exportCsv');
        $this->assertSame(\Symfony\Component\HttpFoundation\StreamedResponse::class, $adminExport->getReturnType()?->getName());
    }

    public function test_workspace_component_includes_directory_table_details_modal_and_download_csv(): void
    {
        $workspace = file_get_contents(resource_path('js/Pages/Construction/Billing/BillingWorkspace.jsx'));

        // Directory table headers & features
        $this->assertStringContainsString('Projects & Clients Directory', $workspace);
        $this->assertStringContainsString('Download CSV', $workspace);
        $this->assertStringContainsString('View Details', $workspace);

        // Required columns and fields
        $this->assertStringContainsString('Khasra / Site Area', $workspace);
        $this->assertStringContainsString('Task Breakdown', $workspace);
        $this->assertStringContainsString('Checklist Progress', $workspace);
        $this->assertStringContainsString('khasra_number', $workspace);

        // Modal breakdown
        $this->assertStringContainsString('Client Information', $workspace);
        $this->assertStringContainsString('Land & Site Details', $workspace);
        $this->assertStringContainsString('Task Execution Breakdown', $workspace);
        $this->assertStringContainsString('Checklist Execution Status', $workspace);
        $this->assertStringContainsString('Financial & Billing Overview', $workspace);
    }
}
