<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminRoleManagementMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard('superadmin')->user();
        $isProductSuperAdmin = $user instanceof SuperAdmin && (int) $user->id === 1;
        $isCompanySuperAdmin = $user instanceof SuperAdmin && $user->company_id !== null;

        abort_unless($isProductSuperAdmin || $isCompanySuperAdmin, 403);

        return $next($request);
    }
}
