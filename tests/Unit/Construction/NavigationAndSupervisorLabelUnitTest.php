<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class NavigationAndSupervisorLabelUnitTest extends TestCase
{
    public function test_super_admin_sidebar_does_not_contain_survey_planning(): void
    {
        $sidebarPath = resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $content = file_get_contents($sidebarPath);
        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Survey Planning["\']/',
            $content,
            'SuperAdmin sidebar must not contain Survey Planning'
        );
    }

    public function test_admin_sidebar_does_not_contain_survey_planning(): void
    {
        $sidebarPath = resource_path('js/Pages/Admin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $content = file_get_contents($sidebarPath);
        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Survey Planning["\']/',
            $content,
            'Admin sidebar must not contain Survey Planning'
        );
    }

    public function test_authenticated_layouts_do_not_contain_survey_planning_responsive_nav(): void
    {
        $superAuthLayout = resource_path('js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($superAuthLayout);
        $superContent = file_get_contents($superAuthLayout);
        $this->assertDoesNotMatchRegularExpression(
            '/Survey Planning/',
            $superContent,
            'SuperAdmin AuthenticatedLayout must not contain Survey Planning'
        );

        $adminAuthLayout = resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($adminAuthLayout);
        $adminContent = file_get_contents($adminAuthLayout);
        $this->assertDoesNotMatchRegularExpression(
            '/Survey Planning/',
            $adminContent,
            'Admin AuthenticatedLayout must not contain Survey Planning'
        );
    }

    public function test_construction_shell_does_not_contain_survey_planning_nav_pill(): void
    {
        $shellPath = resource_path('js/Pages/Construction/Components/ConstructionShell.jsx');
        $this->assertFileExists($shellPath);

        $content = file_get_contents($shellPath);
        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Survey Planning["\']/',
            $content,
            'ConstructionShell must not contain Survey Planning nav pill'
        );
    }
}

