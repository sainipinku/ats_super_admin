<?php

namespace Tests\Feature\Construction;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SidebarProjectLifecycleNavigationFeatureTest extends TestCase
{
    public function test_sidebar_project_lifecycle_retains_projects_and_budget_and_attendance_logs_only(): void
    {
        $superSidebarPath = resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx');
        $this->assertFileExists($superSidebarPath);
        $superContent = file_get_contents($superSidebarPath);

        // Project Lifecycle section retains Projects & Budget and Attendance Logs
        $this->assertMatchesRegularExpression('/section:\s*["\']Project Lifecycle["\']/', $superContent);
        $this->assertMatchesRegularExpression('/label:\s*["\']Projects & Budget["\']/', $superContent);
        $this->assertMatchesRegularExpression('/label:\s*["\']Attendance Logs["\']/', $superContent);

        // Project Lifecycle section must not link to drafting or execution
        $this->assertStringNotContainsString('super.construction.drafting.index', $superContent);
        $this->assertStringNotContainsString('super.construction.execution.index', $superContent);

        $adminSidebarPath = resource_path('js/Pages/Admin/Layouts/Sidebar.jsx');
        $this->assertFileExists($adminSidebarPath);
        $adminContent = file_get_contents($adminSidebarPath);

        $this->assertMatchesRegularExpression('/section:\s*["\']Project Lifecycle["\']/', $adminContent);
        $this->assertMatchesRegularExpression('/label:\s*["\']Projects & Budget["\']/', $adminContent);
        $this->assertMatchesRegularExpression('/label:\s*["\']Attendance Logs["\']/', $adminContent);

        $this->assertStringNotContainsString('admin.construction.drafting.index', $adminContent);
        $this->assertStringNotContainsString('admin.construction.execution.index', $adminContent);
    }

    public function test_construction_shell_pills_exclude_drawing_approval_and_site_execution(): void
    {
        $shellPath = resource_path('js/Pages/Construction/Components/ConstructionShell.jsx');
        $this->assertFileExists($shellPath);
        $content = file_get_contents($shellPath);

        // Neither super nor admin variant in ConstructionShell should link to drafting or execution
        $this->assertStringNotContainsString('super.construction.drafting.index', $content);
        $this->assertStringNotContainsString('super.construction.execution.index', $content);
        $this->assertStringNotContainsString('admin.construction.drafting.index', $content);
        $this->assertStringNotContainsString('admin.construction.execution.index', $content);
    }
}
