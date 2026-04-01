<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class JobRequestController extends Controller
{
    /**
     * Display job requests page (Inertia)
     */
    public function index()
    {
        return Inertia::render('SuperAdmin/JobRequests/Index');
    }

    /**
     * Get all job requests (pending, active, declined)
     */
    public function getAllRequests()
    {
        $jobs = Job::with(['creator', 'approver'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * Get pending job requests
     */
    public function getPendingRequests()
    {
        $jobs = Job::with('creator')
            ->pending()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    /**
     * Get single job full details
     */
    public function show(Job $job)
    {
        $job->load(['creator', 'approver']);

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    /**
     * Approve a job request
     */
    public function approve(Job $job)
    {
        if ($job->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending jobs can be approved.',
            ], 400);
        }

        $superAdminId = Auth::guard('superadmin')->id();
        $job->approve($superAdminId);

        return response()->json([
            'success' => true,
            'message' => 'Job approved successfully.',
            'data' => $job->fresh(['creator', 'approver']),
        ]);
    }

    /**
     * Reject a job request
     */
    public function reject(Request $request, Job $job)
    {
        if ($job->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending jobs can be rejected.',
            ], 400);
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:500',
        ]);

        $superAdminId = Auth::guard('superadmin')->id();
        $job->reject($superAdminId, $validated['rejection_reason'] ?? null);

        return response()->json([
            'success' => true,
            'message' => 'Job rejected successfully.',
            'data' => $job->fresh(['creator', 'approver']),
        ]);
    }

    /**
     * Resend edited job for approval (by Super Admin)
     * This is different from admin resend - allows super admin to send back for changes
     */
    public function requestChanges(Request $request, Job $job)
    {
        $validated = $request->validate([
            'change_request' => 'required|string|max:500',
        ]);

        $superAdminId = Auth::guard('superadmin')->id();
        
        // Add log entry for change request
        $logs = $job->approval_logs ?? [];
        $logs[] = [
            'action' => 'change_requested',
            'user_id' => $superAdminId,
            'reason' => $validated['change_request'],
            'timestamp' => now()->toDateTimeString(),
        ];

        $job->update([
            'status' => 'declined',
            'rejection_reason' => $validated['change_request'],
            'approval_logs' => $logs,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Change request sent to admin.',
            'data' => $job->fresh(['creator', 'approver']),
        ]);
    }

    /**
     * Delete a job request
     */
    public function destroy(Job $job)
    {
        // Delete company image if exists
        if ($job->company_image) {
            $oldPath = str_replace('/storage/', '', $job->company_image);
            \Illuminate\Support\Facades\Storage::disk('public')->delete($oldPath);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job request deleted successfully.',
        ]);
    }

    /**
     * Get job statistics for dashboard
     */
    public function getStatistics()
    {
        $stats = [
            'total' => Job::count(),
            'pending' => Job::pending()->count(),
            'active' => Job::active()->count(),
            'declined' => Job::declined()->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
        ]);
    }
}
