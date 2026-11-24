<?php

namespace Tests\Feature\Construction;

use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\Company;
use App\Models\Project;
use App\Models\SuperAdmin;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class BillingReportDateRangeDownloadFeatureTest extends TestCase
{
    use DatabaseTransactions;

    public function test_superadmin_can_export_billing_csv_filtered_by_date_range(): void
    {
        $superAdmin = SuperAdmin::first();
        if (!$superAdmin) {
            $superAdmin = SuperAdmin::create([
                'name' => 'Super Admin Test',
                'email' => 'superadmin_test_' . Str::random(5) . '@example.com',
                'password' => bcrypt('password'),
                'is_active' => true,
            ]);
        }

        $company = Company::create(['name' => 'Date Range Co ' . Str::random(5), 'status' => 'active']);
        $client = Client::create([
            'company_id' => $company->id,
            'client_code' => 'CL-' . Str::upper(Str::random(5)),
            'name' => 'Test Client ' . Str::random(5),
            'status' => 'active',
        ]);

        // 1. Project within range (November 2026)
        $inRangeProject = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-IN-' . Str::upper(Str::random(4)),
            'name' => 'Project Inside Date Range',
            'slug' => 'prj-in-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'start_date' => '2026-11-10',
            'expected_end_date' => '2026-11-25',
        ]);

        // 2. Project outside range (October 2026)
        $outRangeProject = Project::create([
            'company_id' => $company->id,
            'client_id' => $client->id,
            'project_code' => 'PRJ-OUT-' . Str::upper(Str::random(4)),
            'name' => 'Project Outside Date Range',
            'slug' => 'prj-out-' . Str::lower(Str::random(4)),
            'status' => 'active',
            'start_date' => '2026-10-01',
            'expected_end_date' => '2026-10-15',
        ]);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.construction.billing.export-csv', [
                'start_date' => '2026-11-01',
                'end_date' => '2026-11-30',
            ]));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('Content-Type'));

        ob_start();
        $response->sendContent();
        $csvContent = (string) ob_get_clean();

        // Must contain in-range project
        $this->assertStringContainsString($inRangeProject->project_code, $csvContent, 'In-range project must be included in CSV');
        $this->assertStringContainsString('Project Inside Date Range', $csvContent);

        // Must NOT contain out-of-range project
        $this->assertStringNotContainsString($outRangeProject->project_code, $csvContent, 'Out-of-range project must NOT be included in CSV');

        // Must NOT contain task and checklist metrics columns
        $this->assertStringNotContainsString('Total Tasks', $csvContent);
        $this->assertStringNotContainsString('Completed Tasks', $csvContent);
        $this->assertStringNotContainsString('In Progress Tasks', $csvContent);
        $this->assertStringNotContainsString('Pending Tasks', $csvContent);
        $this->assertStringNotContainsString('Total Checklists', $csvContent);
        $this->assertStringNotContainsString('Completed Checklists', $csvContent);
    }

    public function test_billing_service_export_projects_billing_csv_with_date_range_includes_date_headers(): void
    {
        $activityService = $this->createMock(ConstructionActivityService::class);
        $billingService = new ConstructionBillingService($activityService);

        $mockData = [
            [
                'project_code' => 'PRJ-200',
                'name' => 'Jaipur Metro Phase 2',
                'current_stage' => 'execution',
                'status' => 'active',
                'priority' => 'high',
                'start_date' => '2026-11-05',
                'expected_end_date' => '2026-11-28',
                'created_at' => '2026-11-01',
                'client' => [
                    'client_code' => 'CLI-200',
                    'name' => 'Metro Rail Corp',
                    'company_name' => 'MRC Ltd',
                    'contact_person' => 'Sunil Gupta',
                    'phone' => '9876543211',
                    'email' => 'sunil@mrc.gov.in',
                ],
                'khasra_number' => 'KH-100',
                'site_area' => '25000 sq ft',
                'task_counts' => ['total' => 10, 'completed' => 5, 'in_progress' => 3, 'pending' => 2],
                'checklist_counts' => ['total' => 15, 'completed' => 10],
                'billing_summary' => ['total_billed' => 500000.0, 'total_paid' => 200000.0, 'outstanding' => 300000.0],
            ],
        ];

        $response = $billingService->exportProjectsBillingCsv($mockData, '2026-11-01', '2026-11-30');

        $this->assertSame(200, $response->getStatusCode());
        $contentDisposition = (string) $response->headers->get('Content-Disposition');
        $this->assertStringContainsString('2026-11-01', $contentDisposition);
        $this->assertStringContainsString('2026-11-30', $contentDisposition);

        ob_start();
        $response->sendContent();
        $csvContent = (string) ob_get_clean();

        $this->assertStringContainsString('PRJ-200', $csvContent);
        $this->assertStringContainsString('Start Date', $csvContent);
    }
}
