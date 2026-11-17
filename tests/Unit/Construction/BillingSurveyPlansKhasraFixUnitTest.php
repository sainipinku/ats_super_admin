<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class BillingSurveyPlansKhasraFixUnitTest extends TestCase
{
    public function test_billing_controllers_do_not_query_non_existent_khasra_no_column(): void
    {
        $superController = file_get_contents(base_path('app/Http/Controllers/SuperAdmin/Construction/BillingController.php'));
        $adminController = file_get_contents(base_path('app/Http/Controllers/Admin/Construction/BillingController.php'));
        $billingService = file_get_contents(base_path('app/Services/Construction/ConstructionBillingService.php'));

        $this->assertStringNotContainsString('khasra_no', $superController, 'SuperAdmin BillingController must not query khasra_no');
        $this->assertStringNotContainsString('khasra_no', $adminController, 'Admin BillingController must not query khasra_no');
        $this->assertStringNotContainsString('khasra_no', $billingService, 'ConstructionBillingService must not reference khasra_no');

        $this->assertStringContainsString('draftingJobs:id,project_id,khasra_number,site_area', $superController);
        $this->assertStringContainsString('draftingJobs:id,project_id,khasra_number,site_area', $adminController);
    }

    public function test_project_controller_validates_and_accepts_khasra_number_and_site_area(): void
    {
        $projectController = file_get_contents(base_path('app/Http/Controllers/SuperAdmin/Construction/ProjectController.php'));

        $this->assertStringContainsString("'khasra_number'", $projectController, 'ProjectController must validate khasra_number');
        $this->assertStringContainsString("'site_area'", $projectController, 'ProjectController must validate site_area');
        $this->assertStringContainsString("'scope_of_work'", $projectController, 'ProjectController must validate scope_of_work');
    }

    public function test_show_jsx_syncs_khasra_number_in_draft_upload_form(): void
    {
        $showJsx = file_get_contents(base_path('resources/js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));

        $this->assertStringContainsString('resolvedKhasraNumber', $showJsx, 'Show.jsx should resolve and sync khasra_number');
    }
}
