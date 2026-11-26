<?php

namespace Tests\Unit\Construction;

use Tests\TestCase;

class PermissionRequestsRemovalUnitTest extends TestCase
{
    public function test_super_admin_sidebar_does_not_contain_permission_requests(): void
    {
        $sidebarPath = resource_path('js/Pages/SuperAdmin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $content = file_get_contents($sidebarPath);

        $this->assertStringNotContainsString(
            'Permission Requests',
            $content,
            'SuperAdmin Sidebar.jsx must not contain Permission Requests label'
        );
        $this->assertStringNotContainsString(
            'super.approval_requests.index',
            $content,
            'SuperAdmin Sidebar.jsx must not link to super.approval_requests.index'
        );
        $this->assertStringNotContainsString(
            'approval_request.',
            $content,
            'SuperAdmin Sidebar.jsx must not reference approval_request permissions'
        );
    }

    public function test_admin_sidebar_and_layout_do_not_contain_permission_requests(): void
    {
        $sidebarPath = resource_path('js/Pages/Admin/Layouts/Sidebar.jsx');
        $this->assertFileExists($sidebarPath);

        $sidebarContent = file_get_contents($sidebarPath);

        $this->assertStringNotContainsString(
            'Permission Requests',
            $sidebarContent,
            'Admin Sidebar.jsx must not contain Permission Requests label'
        );
        $this->assertStringNotContainsString(
            'super.approval_requests.index',
            $sidebarContent,
            'Admin Sidebar.jsx must not link to super.approval_requests.index'
        );
        $this->assertStringNotContainsString(
            'approval_request.',
            $sidebarContent,
            'Admin Sidebar.jsx must not reference approval_request permissions'
        );

        $layoutPath = resource_path('js/Pages/Admin/Layouts/AuthenticatedLayout.jsx');
        $this->assertFileExists($layoutPath);

        $layoutContent = file_get_contents($layoutPath);
        $this->assertStringNotContainsString(
            'approval_request.',
            $layoutContent,
            'Admin AuthenticatedLayout.jsx must not reference approval_request permissions'
        );
        $this->assertStringContainsString(
            'Approve Workflow',
            $layoutContent,
            'Admin AuthenticatedLayout.jsx should retain Approve Workflow button for project.manage'
        );
    }

    public function test_approval_requests_index_page_is_removed(): void
    {
        $pagePath = resource_path('js/Pages/SuperAdmin/ApprovalRequests/Index.jsx');
        $this->assertFileDoesNotExist(
            $pagePath,
            'SuperAdmin/ApprovalRequests/Index.jsx must be removed'
        );
    }

    public function test_permission_denied_page_does_not_include_request_permission_modal_or_form(): void
    {
        $deniedPath = resource_path('js/Pages/Errors/PermissionDenied.jsx');
        $this->assertFileExists($deniedPath);

        $content = file_get_contents($deniedPath);

        $this->assertStringNotContainsString(
            'Request Permission from Super Admin',
            $content,
            'PermissionDenied.jsx must not show Request Permission from Super Admin button'
        );
        $this->assertStringNotContainsString(
            'Request Permission Access',
            $content,
            'PermissionDenied.jsx must not render Request Permission Access modal'
        );
        $this->assertStringNotContainsString(
            'super.permission.request.submit',
            $content,
            'PermissionDenied.jsx must not reference super.permission.request.submit route'
        );
        $this->assertStringNotContainsString(
            '<form',
            $content,
            'PermissionDenied.jsx must not render permission request form'
        );
        $this->assertStringContainsString(
            'Return to Dashboard',
            $content,
            'PermissionDenied.jsx must preserve Return to Dashboard navigation link'
        );
    }

    public function test_approval_request_controller_retains_submit_delete_request_and_removes_permission_queue_actions(): void
    {
        $controllerPath = app_path('Http/Controllers/SuperAdmin/ApprovalRequestController.php');
        $this->assertFileExists($controllerPath);

        $content = file_get_contents($controllerPath);

        $this->assertMatchesRegularExpression(
            '/public function submitDeleteRequest\s*\(/',
            $content,
            'ApprovalRequestController must retain submitDeleteRequest for DeleteActionButton'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public function index\s*\(/',
            $content,
            'ApprovalRequestController must remove index action'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public function submitPermissionRequest\s*\(/',
            $content,
            'ApprovalRequestController must remove submitPermissionRequest action'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public function approve\s*\(/',
            $content,
            'ApprovalRequestController must remove approve action'
        );
        $this->assertDoesNotMatchRegularExpression(
            '/public function reject\s*\(/',
            $content,
            'ApprovalRequestController must remove reject action'
        );
    }

    public function test_permission_seeders_exclude_approval_request_permissions(): void
    {
        $seederPaths = [
            database_path('seeders/PermissionSeeder.php'),
            database_path('seeders/SuperAdminPermissionSeeder.php'),
        ];

        foreach ($seederPaths as $path) {
            $this->assertFileExists($path);
            $content = file_get_contents($path);

            $this->assertStringNotContainsString(
                'approval_request.view',
                $content,
                basename($path) . ' must not seed approval_request.view'
            );
            $this->assertStringNotContainsString(
                'approval_request.approve',
                $content,
                basename($path) . ' must not seed approval_request.approve'
            );
            $this->assertStringNotContainsString(
                'approval_request.reject',
                $content,
                basename($path) . ' must not seed approval_request.reject'
            );
        }
    }
}
