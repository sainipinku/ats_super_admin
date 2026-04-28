<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class JobRequestController extends Controller
{
    private function publicDiskPathFromDbValue(?string $value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            $path = parse_url($value, PHP_URL_PATH) ?: '';
        } else {
            $path = $value;
        }

        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }

        return ltrim($path, '/');
    }

    /**
     * Display job requests page (Inertia)
     */
    public function index()
    {
        return Inertia::render('SuperAdmin/JobRequests/Index');
    }

    /**
     * Display all jobs listing page (Inertia)
     */
    public function allJobs()
    {
        return Inertia::render('SuperAdmin/JobRequests/AllJobs');
    }

    public function applicationsIndex()
    {
        return Inertia::render('SuperAdmin/JobApplications/Index');
    }

    public function listApplications(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 50));

        $query = JobApplication::query()
            ->with([
                'job' => function ($q) {
                    $q->select('id', 'title', 'company', 'created_by');
                },
                'candidate' => function ($q) {
                    $q->select('id', 'name', 'email', 'phone', 'image');
                },
            ])
            ->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->string('status')->toString());
        }

        if ($request->filled('job_id')) {
            $query->where('job_id', (int) $request->input('job_id'));
        }

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('candidate_name', 'like', "%{$search}%")
                    ->orWhere('candidate_email', 'like', "%{$search}%")
                    ->orWhere('candidate_phone', 'like', "%{$search}%")
                    ->orWhereHas('job', function ($jq) use ($search) {
                        $jq->where('title', 'like', "%{$search}%")
                            ->orWhere('company', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $applications = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
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
        $job->load([
            'creator',
            'approver',
            'applications' => function ($q) {
                $q->orderByDesc('created_at');
            },
            'applications.candidate',
        ]);

        return response()->json([
            'success' => true,
            'data' => $job,
        ]);
    }

    public function update(Request $request, Job $job)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'job_type' => 'required|string|max:50',
            'experience' => 'nullable|string|max:100',
            'salary' => 'nullable|string|max:100',
            'skills' => 'nullable',
            'perks' => 'nullable',
            'key_responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'last_date' => 'nullable|date',
            'company_image' => 'nullable|image|max:5120',
        ]);

        if (array_key_exists('skills', $validated) && is_string($validated['skills'])) {
            $validated['skills'] = json_decode($validated['skills'], true) ?: [];
        }
        if (array_key_exists('perks', $validated) && is_string($validated['perks'])) {
            $validated['perks'] = json_decode($validated['perks'], true) ?: [];
        }

        if (array_key_exists('skills', $validated) && is_array($validated['skills']) === false && $validated['skills'] !== null) {
            $validated['skills'] = [];
        }
        if (array_key_exists('perks', $validated) && is_array($validated['perks']) === false && $validated['perks'] !== null) {
            $validated['perks'] = [];
        }

        if ($request->hasFile('company_image')) {
            $oldValue = $job->getRawOriginal('company_image');
            $oldPath = $this->publicDiskPathFromDbValue($oldValue);
            if (!empty($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('company_image')->store('job-images', 'public');
            $validated['company_image'] = $path;
        }

        $job->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Job updated successfully.',
            'data' => $job->fresh(['creator', 'approver']),
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
        $oldValue = $job->getRawOriginal('company_image');
        $oldPath = $this->publicDiskPathFromDbValue($oldValue);
        if (!empty($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job request deleted successfully.',
        ]);
    }

    /**
     * Permanently close a job
     */
    public function close(Job $job)
    {
        // Only active or inactive jobs can be closed
        if (!in_array($job->status, ['active', 'inactive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only active or inactive jobs can be closed.',
            ], 400);
        }

        $superAdminId = Auth::guard('superadmin')->id();
        
        // Add log entry for closing
        $logs = $job->approval_logs ?? [];
        $logs[] = [
            'action' => 'closed',
            'user_id' => $superAdminId,
            'timestamp' => now()->toDateTimeString(),
        ];

        $job->update([
            'status' => 'closed',
            'approval_logs' => $logs,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job closed successfully.',
            'data' => $job->fresh(['creator', 'approver']),
        ]);
    }

    /**
     * Toggle job active/inactive status (Super Admin)
     */
    public function toggleStatus(Request $request, Job $job)
    {
        $validated = $request->validate([
            'status' => 'required|in:active,inactive',
        ]);

        // Allow toggling if job is approved (active, inactive) or closed (can be reactivated)
        if (!in_array($job->status, ['active', 'inactive', 'closed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only approved or closed jobs can be toggled.',
            ], 400);
        }

        $job->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Job status updated successfully.',
            'data' => $job->fresh(['creator', 'approver']),
        ]);
    }

    public function applicationDecision(Request $request, JobApplication $application)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $newStatus = $validated['action'] === 'approve' ? 'shortlisted' : 'rejected';

        if (!in_array($application->status, ['pending', 'shortlisted', 'waiting_list', 'hired', 'not_selected', 'rejected'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid application status.',
            ], 400);
        }

        $application->update([
            'status' => $newStatus,
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => null,
        ]);

        $application->load([
            'job' => function ($q) {
                $q->select('id', 'title', 'company', 'created_by');
            },
            'candidate' => function ($q) {
                $q->select('id', 'name', 'email', 'phone');
            },
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data' => $application,
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

    /**
     * Display all job applicants page (Inertia) - Super Admin sees all
     */
    public function applicants()
    {
        return Inertia::render('SuperAdmin/JobRequests/JobApplicants');
    }

    /**
     * Get all applicants for all jobs (Super Admin)
     */
    public function getAllApplicants(Request $request)
    {
        $query = JobApplication::with(['job' => function($q) {
                $q->select('id', 'title', 'company', 'location', 'job_type', 'created_by');
            }, 'candidate' => function($q) {
                $q->select('id', 'name', 'email', 'phone', 'image');
            }, 'reviewer' => function($q) {
                $q->select('id', 'name');
            }])
            ->orderBy('created_at', 'desc');

        // Filter by status
        if ($request->has('status') && $request->status) {
            $query->where('status', $request->status);
        }

        // Filter by job
        if ($request->has('job_id') && $request->job_id) {
            $query->where('job_id', $request->job_id);
        }

        // Filter by admin/creator
        if ($request->has('admin_id') && $request->admin_id) {
            $query->whereHas('job', function($q) use ($request) {
                $q->where('created_by', $request->admin_id);
            });
        }

        // Search by candidate name, email, or job title
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('candidate_name', 'like', "%{$search}%")
                  ->orWhere('candidate_email', 'like', "%{$search}%")
                  ->orWhereHas('job', function($jq) use ($search) {
                      $jq->where('title', 'like', "%{$search}%");
                  });
            });
        }

        $applications = $query->paginate(12)->withQueryString();

        // Get all jobs for filter dropdown
        $jobs = Job::select('id', 'title', 'company')
            ->orderBy('title')
            ->get();

        // Status counts
        $statusCounts = [
            'pending' => JobApplication::where('status', 'pending')->count(),
            'shortlisted' => JobApplication::where('status', 'shortlisted')->count(),
            'waiting_list' => JobApplication::where('status', 'waiting_list')->count(),
            'hired' => JobApplication::where('status', 'hired')->count(),
            'not_selected' => JobApplication::where('status', 'not_selected')->count(),
            'rejected' => JobApplication::where('status', 'rejected')->count(),
            'total' => JobApplication::count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $applications,
            'jobs' => $jobs,
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $request->status ?? '',
                'job_id' => $request->job_id ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    /**
     * Get single applicant details (Super Admin)
     */
    public function getApplicantDetails(JobApplication $application)
    {
        $application->load(['job', 'candidate', 'reviewer']);

        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    /**
     * Update applicant status (Super Admin)
     */
    public function updateApplicantStatus(Request $request, JobApplication $application)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,shortlisted,waiting_list,hired,not_selected,rejected',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $application->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('superadmin')->id(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application status updated successfully.',
            'data' => $application->fresh(),
        ]);
    }
}
