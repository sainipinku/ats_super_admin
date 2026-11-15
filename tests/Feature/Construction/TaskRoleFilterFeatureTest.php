<?php

namespace Tests\Feature\Construction;

use Tests\TestCase;

class TaskRoleFilterFeatureTest extends TestCase
{
    public function test_projects_show_defines_role_filter_badges_for_all_unassigned_and_roles(): void
    {
        $showPath = resource_path('js/Pages/SuperAdmin/Construction/Projects/Show.jsx');
        $this->assertFileExists($showPath);

        $content = file_get_contents($showPath);

        // 1. Must define selectedRoleFilter state
        $this->assertStringContainsString(
            'selectedRoleFilter',
            $content,
            'Show.jsx must define selectedRoleFilter state for filtering tasks'
        );

        // 2. Must define filteredTaskList derived from mergedTaskList and selectedRoleFilter
        $this->assertStringContainsString(
            'filteredTaskList',
            $content,
            'Show.jsx must compute filteredTaskList based on selectedRoleFilter'
        );

        // 3. Must provide roleFilterOptions containing All and Unassigned
        $this->assertStringContainsString(
            'roleFilterOptions',
            $content,
            'Show.jsx must define roleFilterOptions'
        );

        // 4. Must render filter badges above execution tasks
        $this->assertStringContainsString(
            'Filter Tasks By Assigned Role',
            $content,
            'Show.jsx must display the role filter badge bar above execution tasks'
        );

        // 5. Must handle Unassigned task filtering
        $this->assertStringContainsString(
            'unassigned',
            $content,
            'Show.jsx must support unassigned filter option'
        );
    }
}

