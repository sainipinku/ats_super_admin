<?php

namespace Tests\Feature;

use App\Models\SuperAdmin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class SuperAdminRoleManagementAccessTest extends TestCase
{
    public function test_super_admin_without_company_scope_cannot_open_role_management(): void
    {
        $user = (new SuperAdmin())->forceFill(['id' => 2, 'company_id' => null]);

        $this->actingAs($user, 'superadmin')
            ->get('/super/role/list')
            ->assertForbidden();
    }

    public function test_product_and_company_super_admins_can_pass_role_management_access_check(): void
    {
        $middleware = new \App\Http\Middleware\SuperAdminRoleManagementMiddleware();
        $request = Request::create('/super/role/list');

        Auth::guard('superadmin')->setUser((new SuperAdmin())->forceFill(['id' => 1, 'company_id' => null]));
        $this->assertSame(200, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());

        Auth::guard('superadmin')->setUser((new SuperAdmin())->forceFill(['id' => 2, 'company_id' => 5]));
        $this->assertSame(200, $middleware->handle($request, fn () => response('allowed'))->getStatusCode());
    }
}
