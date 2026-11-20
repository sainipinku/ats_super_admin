<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class SidebarProjectLifecycleNavigationUnitTest extends TestCase
{
    public function test_super_admin_sidebar_does_not_contain_drawing_approval_or_site_execution(): void
    {
        $sidebarPath = resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $content = file_get_contents($sidebarPath);

        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Drawing Approval["\']/',
            $content,
            'SuperAdmin sidebar must not contain Drawing Approval under Project Lifecycle'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Site Execution["\']/',
            $content,
            'SuperAdmin sidebar must not contain Site Execution under Project Lifecycle'
        );
    }

    public function test_admin_sidebar_does_not_contain_drawing_approval_or_site_execution(): void
    {
        $sidebarPath = resource_path('js/Pages/Admin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $content = file_get_contents($sidebarPath);

        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Drawing Approval["\']/',
            $content,
            'Admin sidebar must not contain Drawing Approval under Project Lifecycle'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/label:\s*["\']Site Execution["\']/',
            $content,
            'Admin sidebar must not contain Site Execution under Project Lifecycle'
        );
    }

    public function test_super_admin_and_admin_authenticated_layouts_do_not_contain_drawing_approval_or_site_execution(): void
    {
        $superAuthLayout = resource_path('js/Pages/SuperAdmin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($superAuthLayout);
        $superContent = file_get_contents($superAuthLayout);

        $this->assertDoesNotMatchRegularExpression(
            '/Drawing Approval/',
            $superContent,
            'SuperAdmin AuthenticatedLayout mobile menu must not contain Drawing Approval'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/Site Execution/',
            $superContent,
            'SuperAdmin AuthenticatedLayout mobile menu must not contain Site Execution'
        );

        $adminAuthLayout = resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($adminAuthLayout);
        $adminContent = file_get_contents($adminAuthLayout);

        $this->assertDoesNotMatchRegularExpression(
            '/Drawing Approval/',
            $adminContent,
            'Admin AuthenticatedLayout mobile menu must not contain Drawing Approval'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/Site Execution/',
            $adminContent,
            'Admin AuthenticatedLayout mobile menu must not contain Site Execution'
        );
    }

    public function test_construction_shell_does_not_contain_drawing_approval_or_site_execution_for_super_and_admin(): void
    {
        $shellPath = resource_path('js/Pages/Construction/Components/ConstructionShell.jsx');
        $this->assertFileExists($shellPath);

        $content = file_get_contents($shellPath);

        // Under super variant
        $this->assertDoesNotMatchRegularExpression(
            '/super:\s*\{[\s\S]*?label:\s*["\']Drawing Approval["\'][\s\S]*?items:/',
            $content,
            'ConstructionShell super variant items must not contain Drawing Approval'
        );

        $this->assertDoesNotMatchRegularExpression(
            '/admin:\s*\{[\s\S]*?label:\s*["\']Drawing Approval["\'][\s\S]*?items:/',
            $content,
            'ConstructionShell admin variant items must not contain Drawing Approval'
        );
    }
}
