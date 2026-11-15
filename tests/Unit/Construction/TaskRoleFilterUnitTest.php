<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class TaskRoleFilterUnitTest extends TestCase
{
    private string $showContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->showContent = file_get_contents(resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx'));
    }

    public function test_show_component_declares_role_helper_functions(): void
    {
        $this->assertStringContainsString('const normalizeRoleStr', $this->showContent);
        $this->assertStringContainsString('const getRoleIcon', $this->showContent);
        $this->assertStringContainsString('const resolveTaskAssignedMembers', $this->showContent);
        $this->assertStringContainsString('const isTaskUnassigned', $this->showContent);
        $this->assertStringContainsString('const getTaskRoles', $this->showContent);
        $this->assertStringContainsString('const taskMatchesRole', $this->showContent);
    }

    public function test_show_component_renders_all_and_unassigned_filter_badges(): void
    {
        $this->assertStringContainsString('key: "all"', $this->showContent);
        $this->assertStringContainsString('label: "All Tasks"', $this->showContent);
        $this->assertStringContainsString('key: "unassigned"', $this->showContent);
        $this->assertStringContainsString('label: "Unassigned"', $this->showContent);
    }

    public function test_show_component_includes_clear_filter_button_and_count_display(): void
    {
        $this->assertStringContainsString('Clear filter', $this->showContent);
        $this->assertStringContainsString('setSelectedRoleFilter("all")', $this->showContent);
        $this->assertStringContainsString('activeFilterOption.label', $this->showContent);
    }

    public function test_show_component_includes_role_icons_for_common_construction_roles(): void
    {
        // Icons for driver, surveyor, draft/cad, and default
        $this->assertStringContainsString('driver', $this->showContent);
        $this->assertStringContainsString('survey', $this->showContent);
        $this->assertStringContainsString('draft', $this->showContent);
    }

    public function test_show_component_handles_empty_filter_results_gracefully(): void
    {
        $this->assertStringContainsString('No tasks found for', $this->showContent);
        $this->assertStringContainsString('Show All Tasks', $this->showContent);
    }

    public function test_show_component_strictly_uses_official_roles_listing_without_dynamic_designation_leakage(): void
    {
        // roleFilterOptions must strictly map over (roles || [])
        $this->assertStringContainsString('(roles || []).forEach', $this->showContent);

        // It must NOT dynamically scan mergedTaskList to add arbitrary member designations
        $this->assertDoesNotMatchRegularExpression(
            '/mergedTaskList\.forEach\([^)]*taskRolesInfo\.roles\.forEach/',
            $this->showContent,
            'Show.jsx must not dynamically inject arbitrary member designations/custom labels as filter badges'
        );
    }
}

