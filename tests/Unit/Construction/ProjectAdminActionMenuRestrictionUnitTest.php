<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectAdminActionMenuRestrictionUnitTest extends TestCase
{
    public function test_projects_index_component_restricts_actions_menu_for_company_admins(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // Must define company admin check
        $this->assertMatchesRegularExpression(
            '/isCompanyAdmin\s*=/i',
            $code,
            'Index.jsx must identify company admins to restrict actions'
        );

        // Must check per-project edit and delete permission
        $this->assertMatchesRegularExpression(
            '/canEditProjectFor/i',
            $code,
            'Index.jsx must define canEditProjectFor scoping function'
        );
        $this->assertMatchesRegularExpression(
            '/canDeleteProjectFor/i',
            $code,
            'Index.jsx must define canDeleteProjectFor scoping function'
        );

        // Row action menu must be guarded by row permissions, not raw global hasPerm
        $this->assertStringNotContainsString(
            '{(canEditProject || canDeleteProject) && (',
            $code,
            'Index.jsx must not use global canEditProject || canDeleteProject for row actions dropdown'
        );
    }

    public function test_delete_action_button_does_not_permit_company_admins_to_delete_projects(): void
    {
        $btnPath = resource_path('js/Components/DeleteActionButton.jsx');
        $this->assertFileExists($btnPath);

        $code = file_get_contents($btnPath);

        // DeleteActionButton must disallow non-superadmins for project resource
        $this->assertMatchesRegularExpression(
            "/resType\s*===\s*['\"]project['\"]/i",
            $code,
            'DeleteActionButton.jsx must restrict non-superadmins from requesting project deletion'
        );
    }

    public function test_project_controller_blocks_company_admins_from_update_and_destroy(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // update method must check company admin and abort
        $this->assertMatchesRegularExpression(
            "/abort_if\s*\(\s*!\\\$isGlobalSuperAdmin\s*&&\s*!\\\$isCompanySuperAdmin/i",
            $code,
            'ProjectController::update must abort if actor is a company admin'
        );

        // destroy method must check company admin and abort
        $this->assertMatchesRegularExpression(
            "/destroy\s*\([\s\S]*?abort_if\s*\(\s*!\\\$isGlobalSuperAdmin\s*&&\s*!\\\$isCompanySuperAdmin/i",
            $code,
            'ProjectController::destroy must abort if actor is a company admin'
        );
    }

    public function test_approval_request_controller_blocks_non_superadmins_from_project_delete_request(): void
    {
        $approvalControllerPath = app_path('Http/Controllers/SuperAdmin/ApprovalRequestController.php');
        $this->assertFileExists($approvalControllerPath);

        $code = file_get_contents($approvalControllerPath);

        $this->assertMatchesRegularExpression(
            "/submitDeleteRequest\s*\([\s\S]*?\\\$resourceType\s*===\s*['\"]project['\"]/i",
            $code,
            'ApprovalRequestController::submitDeleteRequest must restrict project delete requests'
        );
    }
}
