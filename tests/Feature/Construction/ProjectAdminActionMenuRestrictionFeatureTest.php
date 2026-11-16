<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class ProjectAdminActionMenuRestrictionFeatureTest extends TestCase
{
    public function test_project_controller_update_enforces_scoping_and_blocks_company_admins(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Verify update has explicit check aborting on company admins
        $this->assertMatchesRegularExpression(
            "/public function update\s*\([\s\S]*?abort_if\s*\(\s*!\\\$isGlobalSuperAdmin\s*&&\s*!\\\$isCompanySuperAdmin\s*,\s*403\s*,\s*['\"]Company admins are not permitted to edit projects\.['\"]\s*\)/i",
            $code,
            'ProjectController::update must abort 403 with descriptive message for company admins'
        );

        // Verify company super admin is locked to their own company projects
        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*\\\$isCompanySuperAdmin\s*\)\s*\{\s*\\\$actorCompanyId\s*=\s*\(int\)\s*\\\$actor->company_id;[\s\S]*?abort\(403\s*,\s*['\"]Unauthorized\. You can only edit projects belonging to your company\.['\"]\)/i",
            $code,
            'ProjectController::update must prevent company super admins from updating projects of other companies'
        );
    }

    public function test_project_controller_destroy_enforces_scoping_and_blocks_company_admins(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/Construction/ProjectController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        // Verify destroy has explicit check aborting on company admins
        $this->assertMatchesRegularExpression(
            "/public function destroy\s*\([\s\S]*?abort_if\s*\(\s*!\\\$isGlobalSuperAdmin\s*&&\s*!\\\$isCompanySuperAdmin\s*,\s*403\s*,\s*['\"]Company admins are not permitted to delete projects\.['\"]\s*\)/i",
            $code,
            'ProjectController::destroy must abort 403 with descriptive message for company admins'
        );

        // Verify company super admin is locked to their own company projects
        $this->assertMatchesRegularExpression(
            "/public function destroy\s*\([\s\S]*?if\s*\(\s*\\\$isCompanySuperAdmin\s*\)\s*\{\s*\\\$actorCompanyId\s*=\s*\(int\)\s*\\\$actor->company_id;[\s\S]*?abort\(403\s*,\s*['\"]Unauthorized\. You can only delete projects belonging to your company\.['\"]\)/i",
            $code,
            'ProjectController::destroy must prevent company super admins from deleting projects of other companies'
        );
    }

    public function test_approval_request_controller_rejects_delete_request_for_projects_from_admins(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/ApprovalRequestController.php');
        $this->assertFileExists($controllerPath);

        $code = file_get_contents($controllerPath);

        $this->assertMatchesRegularExpression(
            "/if\s*\(\s*\\\$resourceType\s*===\s*['\"]project['\"]\s*\)\s*\{[\s\S]*?!\\\$isSuperAdmin[\s\S]*?Company admins are not permitted to delete or request deletion of projects\./i",
            $code,
            'ApprovalRequestController must block company admins from submitting delete requests for projects'
        );
    }

    public function test_projects_index_view_ensures_admins_only_receive_view_details(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // canEditProjectFor returns false for company admin
        $this->assertMatchesRegularExpression(
            "/const canEditProjectFor = \(project\) => \{\s*if \(isCompanyAdmin\) return false;/i",
            $code,
            'canEditProjectFor must return false for isCompanyAdmin'
        );

        // canDeleteProjectFor returns false for company admin
        $this->assertMatchesRegularExpression(
            "/const canDeleteProjectFor = \(project\) => \{\s*if \(isCompanyAdmin\) return false;/i",
            $code,
            'canDeleteProjectFor must return false for isCompanyAdmin'
        );

        // 3-dots actions dropdown is hidden when neither edit nor delete is allowed
        $this->assertStringContainsString(
            'if (!rowCanEdit && !rowCanDelete) return null;',
            $code,
            'Index.jsx must return null (hide 3-dots actions button) if row cannot be edited or deleted'
        );
    }

    public function test_delete_action_button_returns_null_for_project_when_not_superadmin(): void
    {
        $btnPath = resource_path('js/Components/DeleteActionButton.jsx');
        $this->assertFileExists($btnPath);

        $code = file_get_contents($btnPath);

        $this->assertMatchesRegularExpression(
            "/const hasDeletePerm = \(\) => \{[\s\S]*?if \(resType === ['\"]project['\"]\) \{\s*return false;\s*\}/i",
            $code,
            'DeleteActionButton::hasDeletePerm must return false for project resource type when not superadmin'
        );
    }
}
