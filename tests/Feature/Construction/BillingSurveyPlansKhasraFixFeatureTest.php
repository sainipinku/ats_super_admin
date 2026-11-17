<?php

namespace Tests\Feature\Construction;

use App\Http\Controllers\SuperAdmin\Construction\BillingController as SuperBillingController;
use App\Http\Controllers\Admin\Construction\BillingController as AdminBillingController;
use App\Models\Project;
use App\Services\Construction\ConstructionActivityService;
use App\Services\Construction\ConstructionBillingService;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class BillingSurveyPlansKhasraFixFeatureTest extends TestCase
{
    public function test_billing_controllers_do_not_contain_broken_khasra_no_queries(): void
    {
        $superBillingController = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/BillingController.php'));
        $adminBillingController = file_get_contents(app_path('Http/Controllers/Admin/Construction/BillingController.php'));

        $this->assertStringNotContainsString('khasra_no', $superBillingController);
        $this->assertStringNotContainsString('khasra_no', $adminBillingController);

        $this->assertStringContainsString('draftingJobs:id,project_id,khasra_number,site_area', $superBillingController);
        $this->assertStringContainsString('draftingJobs:id,project_id,khasra_number,site_area', $adminBillingController);
    }

    public function test_billing_service_resolves_khasra_and_site_area_from_project_and_drafting_jobs(): void
    {
        $activityService = $this->createMock(ConstructionActivityService::class);
        $billingService = new ConstructionBillingService($activityService);

        // Project 1: has direct khasra_number and site_area
        $project1 = new Project([
            'project_code' => 'PRJ-DIRECT',
            'name' => 'Metro Terminal North',
            'current_stage' => 'planning',
            'status' => 'active',
            'priority' => 'high',
            'khasra_number' => 'KH-101/A',
            'site_area' => '50000 sq ft',
        ]);
        $project1->id = 101;
        $project1->setRelation('client', null);
        $project1->setRelation('company', null);
        $project1->setRelation('draftingJobs', new Collection());
        $project1->setRelation('clientInvoices', new Collection());

        // Project 2: fallback from drafting jobs
        $project2 = new Project([
            'project_code' => 'PRJ-FALLBACK',
            'name' => 'Ring Road Interchange',
            'current_stage' => 'execution',
            'status' => 'active',
            'priority' => 'critical',
            'khasra_number' => null,
            'site_area' => null,
        ]);
        $project2->id = 102;
        $project2->setRelation('client', null);
        $project2->setRelation('company', null);

        $draftJob = new \App\Models\DraftingJob([
            'khasra_number' => 'KH-DRAFT-888',
            'site_area' => '10 Acres',
        ]);
        $project2->setRelation('draftingJobs', new Collection([$draftJob]));
        $project2->setRelation('clientInvoices', new Collection());

        $directory = $billingService->getProjectsBillingDirectory(new Collection([$project1, $project2]));

        $this->assertCount(2, $directory);

        $this->assertSame('KH-101/A', $directory[0]['khasra_number']);
        $this->assertSame('50000 sq ft', $directory[0]['site_area']);

        $this->assertSame('KH-DRAFT-888', $directory[1]['khasra_number']);
        $this->assertSame('10 Acres', $directory[1]['site_area']);

        // Check CSV export includes Khasra Number
        $csvResponse = $billingService->exportProjectsBillingCsv($directory);
        $this->assertSame(200, $csvResponse->getStatusCode());

        ob_start();
        $csvResponse->sendContent();
        $csvOutput = (string) ob_get_clean();

        $this->assertStringContainsString('Khasra Number', $csvOutput);
        $this->assertStringContainsString('KH-101/A', $csvOutput);
        $this->assertStringContainsString('KH-DRAFT-888', $csvOutput);
    }

    public function test_project_controller_validates_and_handles_khasra_number_site_area_and_scope(): void
    {
        $controllerContent = file_get_contents(app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php'));

        $this->assertStringContainsString("'khasra_number' => [", $controllerContent);
        $this->assertStringContainsString("'site_area' => [", $controllerContent);
        $this->assertStringContainsString("'scope_of_work' => [", $controllerContent);
    }

    public function test_ui_components_provide_and_sync_khasra_number_fields(): void
    {
        $showContent = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
        $indexContent = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx'));

        // Show.jsx CAD Drawing modal sync
        $this->assertStringContainsString('resolvedKhasraNumber', $showContent);
        $this->assertStringContainsString('resolvedSiteArea', $showContent);
        $this->assertStringContainsString('resolvedScopeOfWork', $showContent);
        $this->assertStringContainsString('name="khasra_number"', $showContent . $indexContent);

        // Index.jsx New Project and Edit Project modals have inputs for khasra_number
        $this->assertStringContainsString('name="khasra_number"', $indexContent);
        $this->assertStringContainsString('name="site_area"', $indexContent);
        $this->assertStringContainsString('name="scope_of_work"', $indexContent);
        $this->assertStringContainsString('khasra_number: project.khasra_number || ""', $indexContent);
    }
}
