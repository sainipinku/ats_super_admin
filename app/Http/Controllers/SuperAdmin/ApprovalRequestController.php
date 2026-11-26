<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActionApprovalRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApprovalRequestController extends Controller
{
    public function submitDeleteRequest(Request $request)
    {
        $request->validate([
            'resource_type' => 'required|string',
            'resource_id' => 'required|string',
            'resource_name' => 'nullable|string',
            'reason' => 'nullable|string',
        ]);

        $user = Auth::guard('admin')->user()
            ?? Auth::guard('superadmin')->user()
            ?? Auth::guard('member')->user()
            ?? Auth::user();

        $resourceType = strtolower($request->resource_type);
        $resourceId = (string) $request->resource_id;

        if ($resourceType === 'project') {
            $isSuperAdmin = Auth::guard('superadmin')->check()
                || ($user instanceof \App\Models\SuperAdmin);
            if (!$isSuperAdmin) {
                return back()->with('error', 'Company admins are not permitted to delete or request deletion of projects.');
            }
        }

        $existing = ActionApprovalRequest::where('action', 'delete')
            ->where('resource_type', $resourceType)
            ->where('resource_id', $resourceId)
            ->where('status', ActionApprovalRequest::STATUS_PENDING)
            ->first();

        if ($existing) {
            return back()->with('info', 'A deletion request for this item is already pending Super Admin approval.');
        }

        ActionApprovalRequest::create([
            'requester_id' => $user ? $user->id : '0',
            'requester_type' => Auth::guard('superadmin')->check() ? 'superadmin' : 'admin',
            'requester_name' => $user ? $user->name : 'Admin',
            'requester_email' => $user ? $user->email : null,
            'action' => 'delete',
            'resource_type' => $resourceType,
            'resource_id' => $resourceId,
            'resource_name' => $request->resource_name ?? "{$request->resource_type} #{$resourceId}",
            'reason' => $request->reason ?? 'Admin requested deletion of ' . $request->resource_type,
            'status' => ActionApprovalRequest::STATUS_PENDING,
        ]);

        return back()->with('success', 'Delete request submitted to Super Admin for approval.');
    }
}
