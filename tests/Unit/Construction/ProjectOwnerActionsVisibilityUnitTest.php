<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class ProjectOwnerActionsVisibilityUnitTest extends TestCase
{
    public function test_projects_index_jsx_renders_direct_edit_and_delete_actions_for_super_admin_and_project_owner(): void
    {
        $indexPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Index.jsx');
        $this->assertFileExists($indexPath);

        $code = file_get_contents($indexPath);

        // 1. isCompanyAdmin must not override superadmins or project owners
        $this->assertMatchesRegularExpression(
            "/isCompanyAdmin\s*=\s*!isProjectOwner\s*&&\s*!isSuperAdmin/i",
            $code,
            'isCompanyAdmin must strictly exclude isProjectOwner and isSuperAdmin'
        );

        // 2. Direct Edit action button must be rendered in the table Actions column
        $this->assertMatchesRegularExpression(
            "/rowCanEdit\s*&&\s*\(?\s*<button[\s\S]*?openEditModal/is",
            $code,
            'Edit action button must be rendered when rowCanEdit is true'
        );

        // 3. Direct Delete action button must be rendered in the table Actions column
        $this->assertMatchesRegularExpression(
            "/rowCanDelete\s*&&\s*\(?\s*<DeleteActionButton/is",
            $code,
            'DeleteActionButton must be rendered when rowCanDelete is true'
        );

        // 4. View Details must be rendered
        $this->assertStringContainsString('View Details', $code, 'View Details link must be present');
    }
}
