<?php

namespace Tests\Feature\Construction;

use App\Models\ActionApprovalRequest;
use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\Permission;
use App\Models\SuperAdmin;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\SuperAdminPermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PermissionRequestsRemovalFeatureTest extends TestCase
{
    use DatabaseTransactions;

    private function createSuperAdmin(?Company $company = null): SuperAdmin
    {
        return SuperAdmin::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'SA ' . Str::random(5),
            'email' => 'sa_' . Str::lower(Str::random(8)) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'whatsapp_phone' => '9' . random_int(100000000, 999999999),
            'password' => 'password123',
            'status' => 1,
            'company_id' => $company?->id,
            'roles' => ['super_admin'],
        ]);
    }

    private function createAdminMember(SuperAdmin $creator): Member
    {
        return Member::create([
            'uuid' => (string) Str::uuid(),
            'name' => 'Admin Member ' . Str::random(5),
            'username' => 'adm_' . Str::lower(Str::random(6)),
            'slug' => 'adm-' . Str::lower(Str::random(6)),
            'email' => 'adm_' . Str::lower(Str::random(8)) . '@example.com',
            'phone' => '9' . random_int(100000000, 999999999),
            'password' => 'password123',
            'created_by' => $creator->id,
            'status' => Member::STATUS_ACTIVE,
            'roles' => [1],
        ]);
    }

    private function assertFlashedMessage(string $type, string $expectedSubstring): void
    {
        if (session($type) === $expectedSubstring || str_contains((string) session($type, ''), $expectedSubstring)) {
            $this->assertTrue(true);
            return;
        }

        $envelopes = session('flasher::envelopes', []);
        foreach ($envelopes as $envelope) {
            if (is_object($envelope) && method_exists($envelope, 'getNotification')) {
                $notification = $envelope->getNotification();
                if ($notification->getType() === $type && str_contains($notification->getMessage(), $expectedSubstring)) {
                    $this->assertTrue(true);
                    return;
                }
            } else {
                $str = is_string($envelope) ? $envelope : serialize($envelope);
                if (str_contains($str, $type) && str_contains($str, $expectedSubstring)) {
                    $this->assertTrue(true);
                    return;
                }
            }
        }

        $this->fail("Expected flash message [{$type}] containing '{$expectedSubstring}'. Session keys: " . implode(', ', array_keys(session()->all())));
    }

    public function test_permission_request_and_approval_queue_routes_are_removed_while_submit_delete_remains(): void
    {
        $this->assertFalse(Route::has('super.approval_requests.index'));
        $this->assertFalse(Route::has('super.approval_requests.approve'));
        $this->assertFalse(Route::has('super.approval_requests.reject'));
        $this->assertFalse(Route::has('super.permission.request.submit'));
        $this->assertTrue(Route::has('super.approval_requests.submit_delete'));

        $company = Company::create(['name' => 'Route Check Co ' . Str::random(4), 'status' => 'active']);
        $superAdmin = $this->createSuperAdmin($company);

        $this->actingAs($superAdmin, 'superadmin')
            ->get('/super/approval-requests')
            ->assertNotFound();

        $this->actingAs($superAdmin, 'superadmin')
            ->post('/super/permission-request/submit', [
                'permission_slug' => 'project.manage',
                'reason' => 'Need access',
            ])
            ->assertNotFound();
    }

    public function test_delete_request_submission_remains_functional_and_still_blocks_project_deletes_for_admins(): void
    {
        $company = Company::create(['name' => 'Delete Co ' . Str::random(4), 'status' => 'active']);
        $superAdmin = $this->createSuperAdmin($company);
        $adminMember = $this->createAdminMember($superAdmin);

        $clientDeleteRes = $this->actingAs($adminMember, 'admin')
            ->post(route('super.approval_requests.submit_delete'), [
                'resource_type' => 'Client',
                'resource_id' => '999001',
                'resource_name' => 'Acme Client',
                'reason' => 'Duplicate entry',
            ]);

        $clientDeleteRes->assertRedirect();
        $this->assertFlashedMessage('success', 'Delete request submitted to Super Admin for approval.');

        $this->assertDatabaseHas('action_approval_requests', [
            'action' => 'delete',
            'resource_type' => 'client',
            'resource_id' => '999001',
            'status' => ActionApprovalRequest::STATUS_PENDING,
        ]);

        $projectDeleteRes = $this->actingAs($adminMember, 'admin')
            ->post(route('super.approval_requests.submit_delete'), [
                'resource_type' => 'project',
                'resource_id' => '999002',
                'resource_name' => 'Restricted Project',
                'reason' => 'Should be blocked',
            ]);

        $projectDeleteRes->assertRedirect();
        $this->assertFlashedMessage('error', 'Company admins are not permitted to delete or request deletion of projects.');

        $this->assertDatabaseMissing('action_approval_requests', [
            'action' => 'delete',
            'resource_type' => 'project',
            'resource_id' => '999002',
        ]);
    }

    public function test_seeders_remove_approval_request_permissions_and_role_management_omits_them(): void
    {
        Permission::firstOrCreate(
            ['slug' => 'approval_request.view'],
            ['name' => 'View Permission Requests', 'module' => 'Overview', 'description' => 'View pending role and permission requests']
        );
        Permission::firstOrCreate(
            ['slug' => 'approval_request.approve'],
            ['name' => 'Approve Permission Request', 'module' => 'Overview', 'description' => 'Approve user requested permissions']
        );
        Permission::firstOrCreate(
            ['slug' => 'approval_request.reject'],
            ['name' => 'Reject Permission Request', 'module' => 'Overview', 'description' => 'Reject user requested permissions']
        );

        (new PermissionSeeder())->run();
        (new SuperAdminPermissionSeeder())->run();

        $remainingCount = Permission::where('slug', 'like', 'approval_request.%')->count();
        $this->assertSame(0, $remainingCount, 'Seeders must remove all approval_request.* permissions from construction_permissions');

        $company = Company::create(['name' => 'Role Co ' . Str::random(4), 'status' => 'active']);
        $superAdmin = $this->createSuperAdmin($company);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.role.list'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $page->component('SuperAdmin/Roles/List');
            $allPerms = collect($page->toArray()['props']['all_permissions'] ?? []);
            $hasApprovalPerm = $allPerms->contains(fn ($p) => str_starts_with((string) ($p['slug'] ?? ''), 'approval_request.'));
            $this->assertFalse($hasApprovalPerm, 'Role Management all_permissions must not contain approval_request.* slugs');
        });
    }

    public function test_inertia_shared_props_retain_delete_requests_and_omit_pending_approval_count(): void
    {
        $company = Company::create(['name' => 'Inertia Co ' . Str::random(4), 'status' => 'active']);
        $superAdmin = $this->createSuperAdmin($company);

        $response = $this->actingAs($superAdmin, 'superadmin')
            ->get(route('super.profile'));

        $response->assertOk();
        $response->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'] ?? [];
            $this->assertArrayHasKey('delete_approval_requests', $props);
            $this->assertArrayNotHasKey('pending_approval_requests_count', $props['auth'] ?? []);
        });
    }

    public function test_unauthorized_member_renders_permission_denied_403_page_cleanly(): void
    {
        $company = Company::create(['name' => 'Denied Co ' . Str::random(4), 'status' => 'active']);
        $superAdmin = $this->createSuperAdmin($company);
        $member = $this->createAdminMember($superAdmin);

        $siteRole = ConstructionRole::firstOrCreate(
            ['company_id' => $company->id, 'slug' => 'site_employee'],
            ['name' => 'Site Employee', 'status' => 'active', 'is_system_role' => true]
        );
        $member->roles = [$siteRole->slug];
        $member->save();

        $response = $this->actingAs($member, 'member')
            ->get('/member/construction/projects');

        $response->assertStatus(403);
        $response->assertInertia(fn (Assert $page) => $page->component('Errors/PermissionDenied'));
    }

    public function test_ziggy_and_compiled_assets_exclude_removed_permission_request_ui(): void
    {
        $ziggyPath = resource_path('js/ziggy.js');
        $this->assertFileExists($ziggyPath);
        $ziggyContent = file_get_contents($ziggyPath);

        $this->assertStringNotContainsString('"super.approval_requests.index"', $ziggyContent);
        $this->assertStringNotContainsString('"super.approval_requests.approve"', $ziggyContent);
        $this->assertStringNotContainsString('"super.approval_requests.reject"', $ziggyContent);
        $this->assertStringNotContainsString('"super.permission.request.submit"', $ziggyContent);
        $this->assertStringContainsString('"super.approval_requests.submit_delete"', $ziggyContent);

        $manifestPath = public_path('build/manifest.json');
        $this->assertFileExists($manifestPath);
        $manifest = json_decode(file_get_contents($manifestPath), true);
        $this->assertIsArray($manifest);
        $this->assertArrayNotHasKey('resources/js/Pages/SuperAdmin/ApprovalRequests/Index.jsx', $manifest);

        $assetFiles = glob(public_path('build/assets/*.js')) ?: [];
        $this->assertNotEmpty($assetFiles);
        foreach ($assetFiles as $file) {
            $js = file_get_contents($file);
            if (is_string($js)) {
                $this->assertStringNotContainsString('Permission & Access Approval Requests', $js);
                $this->assertStringNotContainsString('Request Permission from Super Admin', $js);
            }
        }
    }
}
