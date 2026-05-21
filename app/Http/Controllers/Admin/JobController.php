<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\OfferLetterMail;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Member;
use App\Models\Notification;
use App\Models\SiteSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Mccarlosen\LaravelMpdf\Facades\LaravelMpdf as PDF;

class JobController extends Controller
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
     * Display job posts page (Inertia)
     */
    public function index()
    {
        $jobs = Job::where('created_by', Auth::guard('admin')->id())
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($job) {
                return [
                    'id' => $job->id,
                    'title' => $job->title,
                    'company' => $job->company,
                    'companyImage' => $job->company_image,
                    'location' => $job->location,
                    'type' => $job->job_type,
                    'experience' => $job->experience,
                    'salary' => $job->salary,
                    'skills' => $job->skills,
                    'perks' => $job->perks,
                    'description' => $job->description,
                    'keyResponsibilities' => $job->key_responsibilities,
                    'qualifications' => $job->qualifications,
                    'lastDate' => $job->last_date,
                    'active' => $job->status === 'active',
                    'status' => $job->status,
                    'createdAt' => $job->created_at,
                    'applicants' => $job->applicants ?? 0,
                ];
            });

        return Inertia::render('Admin/JobPosts/Index', [
            'jobs' => $jobs
        ]);
    }

    /**
     * Display job listing page (Inertia)
     */
    public function listing()
    {
        return Inertia::render('Admin/JobPosts/JobListing');
    }

    public function applicationsIndex()
    {
        return Inertia::render('Admin/JobPosts/JobApplicants', [
            'callingTeamMembers' => $this->callingTeamMembersForAdmin(),
        ]);
    }

    /**
     * Store a new job post
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'job_type' => 'required|string|max:50',
            'experience' => 'nullable|string|max:100',
            'salary' => 'nullable|string|max:100',
            'skills' => 'nullable|string', // JSON string from frontend
            'perks' => 'nullable|string', // JSON string from frontend
            'key_responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'last_date' => 'nullable|date',
            'company_image' => 'nullable|image|max:5120',
            'latitude' => 'nullable|decimal:8,6|between:-90,90',
            'longitude' => 'nullable|decimal:9,6|between:-180,180',
        ]);

        // Convert JSON strings to arrays
        if (!empty($validated['skills'])) {
            $validated['skills'] = json_decode($validated['skills'], true) ?: [];
        }
        if (!empty($validated['perks'])) {
            $validated['perks'] = json_decode($validated['perks'], true) ?: [];
        }

        // Handle company image upload
        if ($request->hasFile('company_image')) {
            $path = $request->file('company_image')->store('job-images', 'public');
            $validated['company_image'] = $path;
        }

        // Set default status and creator
        $validated['status'] = 'pending';
        $validated['created_by'] = Auth::guard('admin')->id();

        $job = Job::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Job post created successfully and sent for approval.',
            'data' => $job,
        ], 201);
    }

    /**
     * Get all jobs created by the authenticated admin
     */
    public function getAdminJobs()
    {
        $jobs = Job::where('created_by', Auth::guard('admin')->id())
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $jobs,
        ]);
    }

    public function listApplications(Request $request)
    {
        $perPage = max(1, min((int) $request->input('per_page', 15), 50));
        $adminId = Auth::guard('admin')->id();

        $query = JobApplication::query()
            ->with([
                'job' => function ($q) {
                    $q->select('id', 'title', 'company', 'created_by');
                },
                'candidate' => function ($q) {
                    $q->select('id', 'name', 'email', 'phone', 'image');
                },
            ])
            ->whereHas('job', function ($q) use ($adminId) {
                $q->where('created_by', $adminId);
            })
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

    public function applications(Job $job)
    {
        if ((int) $job->created_by !== (int) Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $applications = JobApplication::query()
            ->with(['candidate' => function ($q) {
                $q->select('id', 'name', 'email', 'phone', 'image');
            }])
            ->where('job_id', $job->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $applications,
        ]);
    }

    public function applicationDecision(Request $request, JobApplication $application)
    {
        $application->load('job');
        if (!$application->job || (int) $application->job->created_by !== (int) Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $newStatus = $validated['action'] === 'approve' ? 'shortlisted' : 'rejected';

        $application->update([
            'status' => $newStatus,
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => Auth::guard('admin')->id(),
        ]);

        $application->load(['candidate' => function ($q) {
            $q->select('id', 'name', 'email', 'phone', 'image');
        }]);

        return response()->json([
            'success' => true,
            'message' => 'Application updated successfully.',
            'data' => $application,
        ]);
    }

    /**
     * Update a job post
     */
    public function update(Request $request, Job $job)
    {
        // Check if the job belongs to the authenticated admin
        if ($job->created_by !== Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this job.',
            ], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'company' => 'required|string|max:255',
            'description' => 'nullable|string',
            'location' => 'required|string|max:255',
            'job_type' => 'required|string|max:50',
            'experience' => 'nullable|string|max:100',
            'salary' => 'nullable|string|max:100',
            'skills' => 'nullable|string', // JSON string from frontend
            'perks' => 'nullable|string', // JSON string from frontend
            'key_responsibilities' => 'nullable|string',
            'qualifications' => 'nullable|string',
            'last_date' => 'nullable|date',
            'company_image' => 'nullable|image|max:5120',
            'latitude' => 'nullable|decimal:8,6|between:-90,90',
            'longitude' => 'nullable|decimal:9,6|between:-180,180',
        ]);

        // Convert JSON strings to arrays
        if (!empty($validated['skills'])) {
            $validated['skills'] = json_decode($validated['skills'], true) ?: [];
        }
        if (!empty($validated['perks'])) {
            $validated['perks'] = json_decode($validated['perks'], true) ?: [];
        }

        // Handle company image upload
        if ($request->hasFile('company_image')) {
            // Delete old image if exists
            $oldValue = $job->getRawOriginal('company_image');
            $oldPath = $this->publicDiskPathFromDbValue($oldValue);
            if (!empty($oldPath)) {
                Storage::disk('public')->delete($oldPath);
            }

            $path = $request->file('company_image')->store('job-images', 'public');
            $validated['company_image'] = $path;
        }

        // If job was declined, resubmit for approval
        if ($job->status === 'declined') {
            $validated['status'] = 'pending';
            $validated['resubmitted_at'] = now();
            $validated['rejection_reason'] = null;
        }

        $job->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Job post updated successfully.',
            'data' => $job,
        ]);
    }

    /**
     * Delete a job post
     */
    public function destroy(Job $job)
    {
        // Check if the job belongs to the authenticated admin
        if ($job->created_by !== Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this job.',
            ], 403);
        }

        // Delete company image if exists
        $oldValue = $job->getRawOriginal('company_image');
        $oldPath = $this->publicDiskPathFromDbValue($oldValue);
        if (!empty($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        $job->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job post deleted successfully.',
        ]);
    }

    /**
     * Resend a declined job for approval
     */
    public function resend(Job $job)
    {
        // Check if the job belongs to the authenticated admin
        if ($job->created_by !== Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to resend this job.',
            ], 403);
        }

        if ($job->status !== 'declined') {
            return response()->json([
                'success' => false,
                'message' => 'Only declined jobs can be resent for approval.',
            ], 400);
        }

        $job->update([
            'status' => 'pending',
            'resubmitted_at' => now(),
            'rejection_reason' => null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Job resent for approval successfully.',
            'data' => $job,
        ]);
    }

    /**
     * Toggle job active/inactive status
     */
    public function toggleStatus(Request $request, Job $job)
    {
        // Check if the job belongs to the authenticated admin
        if ($job->created_by !== Auth::guard('admin')->id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this job status.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:active,inactive,closed',
        ]);

        // If job is already closed, only super admin can reactivate it
        if ($job->status === 'closed' && in_array($validated['status'], ['active', 'inactive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Closed jobs can only be reactivated by Super Admin.',
            ], 403);
        }

        // Admin can only close their own jobs that are active or inactive
        if ($validated['status'] === 'closed' && !in_array($job->status, ['active', 'inactive'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only active or inactive jobs can be closed.',
            ], 400);
        }

        $job->update(['status' => $validated['status']]);

        return response()->json([
            'success' => true,
            'message' => 'Job status updated successfully.',
            'data' => $job,
        ]);
    }

    /**
     * Display job applicants listing page (Inertia)
     */
    public function applicants()
    {
        return Inertia::render('Admin/JobPosts/JobApplicants', [
            'callingTeamMembers' => $this->callingTeamMembersForAdmin(),
        ]);
    }

    /**
     * Get all applicants for jobs created by the authenticated admin
     */
    public function getApplicants(Request $request)
    {
        $adminId = Auth::guard('admin')->id();

        $jobIds = Job::where('created_by', $adminId)->pluck('id');

        $query = JobApplication::query()
            ->with([
                'job' => function ($q) {
                    $q->select('id', 'title', 'company', 'location', 'job_type');
                },
                'candidate' => function ($q) {
                    $q->select('id', 'name', 'email', 'phone', 'image');
                },
                'assignedCallingTeamMember' => function ($q) {
                    $q->select('id', 'name', 'email', 'phone');
                },
            ])
            ->whereIn('job_id', $jobIds)
            ->orderByDesc('updated_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('job_id')) {
            $query->where('job_id', $request->job_id);
        }

        if ($request->filled('calling_team_member_id')) {
            $query->where('assigned_calling_team_member_id', $request->calling_team_member_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
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

        $applications = $query->paginate(12)->withQueryString();

        $jobs = Job::where('created_by', $adminId)
            ->select('id', 'title', 'company')
            ->orderBy('title')
            ->get();

        $statusCounts = [
            'applied' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'applied')->count(),
            'viewed' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'viewed')->count(),
            'shortlisted' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'shortlisted')->count(),
            'assigned_to_calling_member' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'assigned_to_calling_member')->count(),
            'calling_in_progress' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'calling_in_progress')->count(),
            'calling_approved' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'calling_approved')->count(),
            'calling_rejected' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'calling_rejected')->count(),
            'admin_review' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'admin_review')->count(),
            'offer_letter_generated' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'offer_letter_generated')->count(),
            'rejected' => JobApplication::whereIn('job_id', $jobIds)->where('status', 'rejected')->count(),
            'total' => JobApplication::whereIn('job_id', $jobIds)->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $applications,
            'jobs' => $jobs,
            'callingTeamMembers' => $this->callingTeamMembersForAdmin(),
            'statusCounts' => $statusCounts,
            'filters' => [
                'status' => $request->status ?? '',
                'job_id' => $request->job_id ?? '',
                'calling_team_member_id' => $request->calling_team_member_id ?? '',
                'search' => $request->search ?? '',
            ],
        ]);
    }

    /**
     * Get single applicant details
     */
    public function getApplicantDetails(JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        // Check if the application belongs to a job created by this admin
        if ($application->job->created_by !== $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this application.',
            ], 403);
        }

        if ($application->status === 'applied') {
            $application->update([
                'status' => 'viewed',
                'reviewed_at' => now(),
                'reviewed_by' => $adminId,
            ]);
        }

        $application->load(['job', 'candidate', 'reviewer', 'assignedCallingTeamMember']);

        return response()->json([
            'success' => true,
            'data' => $application,
        ]);
    }

    /**
     * Update applicant status
     */
    public function updateApplicantStatus(Request $request, JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        // Check if the application belongs to a job created by this admin
        if ($application->job->created_by !== $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this application.',
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'required|in:applied,viewed,shortlisted,waiting_list,hired,not_selected,rejected,assigned_to_calling_member,calling_in_progress,calling_approved,calling_rejected,admin_review,offer_letter_generated',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $application->update([
            'status' => $validated['status'],
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Application status updated successfully.',
            'data' => $application->fresh(['job', 'candidate', 'assignedCallingTeamMember']),
        ]);
    }

    public function adminFinalReview(Request $request, JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        $application->load('job');

        if (!$application->job || (int) $application->job->created_by !== (int) $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this application.',
            ], 403);
        }

        $validated = $request->validate([
            'decision' => 'required|in:approved,rejected,follow_up,no_response',
            'decision_reason' => 'nullable|string|max:5000',
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        if ($validated['decision'] === 'approved' && blank($validated['decision_reason'] ?? null)) {
            return response()->json([
                'success' => false,
                'message' => 'Approval reason is required.',
            ], 422);
        }

        $application->update([
            'status' => 'admin_review',
            'admin_final_decision' => $validated['decision'],
            'admin_final_decision_reason' => $validated['decision_reason'] ?? null,
            'admin_final_decision_updated_at' => now(),
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
            'offer_letter_triggered_at' => $validated['decision'] === 'approved'
                ? $application->offer_letter_triggered_at
                : null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Final review saved successfully.',
            'data' => $application->fresh(['job', 'candidate', 'assignedCallingTeamMember']),
        ]);
    }

    public function generateOfferLetter(Request $request, JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        $application->load('job');

        if (!$application->job || (int) $application->job->created_by !== (int) $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to generate offer for this application.',
            ], 403);
        }

        if ($application->admin_final_decision !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Admin approval is required before generating the offer letter.',
            ], 422);
        }

        $validated = $request->validate([
            'offer_salary_package' => ['required', 'string', 'max:255'],
            'offer_joining_date' => ['required', 'date'],
        ]);

        $setting = SiteSetting::first();
        $relativePath = 'offer_letters/offer-letter-' . $application->uuid . '.pdf';

        Storage::disk('public')->makeDirectory('offer_letters');

        $pdf = PDF::loadView('pdf.offer_letter', $this->offerLetterViewData(
            $application->fresh(['job', 'candidate']),
            $setting,
            $validated['offer_salary_package'],
            $validated['offer_joining_date']
        ));

        $pdf->save(storage_path('app/public/' . $relativePath));

        $application->update([
            'offer_salary_package' => $validated['offer_salary_package'],
            'offer_joining_date' => $validated['offer_joining_date'],
            'offer_letter_path' => $relativePath,
            'status' => 'offer_letter_generated',
            'offer_letter_triggered_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        Notification::create([
            'model' => 'member',
            'listing_id' => $application->candidate_id,
            'job_id' => $application->job_id,
            'type' => 'offer_letter_generation_requested',
            'status' => 'unread',
            'data' => $this->notificationData($application, [
                'offer_letter_triggered_at' => $application->fresh()->offer_letter_triggered_at,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offer letter generated successfully.',
            'data' => $application->fresh(['job', 'candidate', 'assignedCallingTeamMember']),
        ]);
    }

    public function downloadOfferLetter(JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        $application->load('job');

        if (!$application->job || (int) $application->job->created_by !== (int) $adminId) {
            abort(403, 'Unauthorized to download this offer letter.');
        }

        if (!$application->offer_letter_path || !Storage::disk('public')->exists($application->offer_letter_path)) {
            abort(404, 'Offer letter not found.');
        }

        $filename = 'offer-letter-' . Str::slug($application->candidate_name ?: 'candidate') . '.pdf';

        return Storage::disk('public')->download($application->offer_letter_path, $filename);
    }

    public function sendOfferLetterEmail(JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        $application->load(['job', 'candidate']);

        if (!$application->job || (int) $application->job->created_by !== (int) $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to send this offer letter.',
            ], 403);
        }

        if ($application->admin_final_decision !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Admin approval is required before sending the offer letter.',
            ], 422);
        }

        if (blank($application->candidate_email)) {
            return response()->json([
                'success' => false,
                'message' => 'Candidate email is missing.',
            ], 422);
        }

        if (!$application->offer_letter_path || !Storage::disk('public')->exists($application->offer_letter_path)) {
            return response()->json([
                'success' => false,
                'message' => 'Generate the offer letter PDF first.',
            ], 422);
        }

        Mail::to($application->candidate_email)->send(
            new OfferLetterMail(
                $application,
                SiteSetting::first(),
                storage_path('app/public/' . $application->offer_letter_path)
            )
        );

        $application->update([
            'offer_letter_sent_at' => now(),
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Offer letter email sent successfully.',
            'data' => $application->fresh(['job', 'candidate', 'assignedCallingTeamMember']),
        ]);
    }

    public function assignCallingTeam(Request $request, JobApplication $application)
    {
        $adminId = Auth::guard('admin')->id();

        $application->load('job');

        if (!$application->job || (int) $application->job->created_by !== (int) $adminId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to assign this application.',
            ], 403);
        }

        $validated = $request->validate([
            'calling_team_member_id' => ['required', 'integer'],
            'admin_notes' => 'nullable|string|max:5000',
        ]);

        $callingTeamMember = Member::query()
            ->where('id', $validated['calling_team_member_id'])
            ->where('assigned_admin_id', $adminId)
            ->where('is_calling_team', true)
            ->where('status', 1)
            ->first();

        if (!$callingTeamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Selected calling team member is invalid.',
            ], 422);
        }

        $application->update([
            'assigned_calling_team_member_id' => $callingTeamMember->id,
            'assigned_to_calling_team_at' => now(),
            'status' => 'assigned_to_calling_member',
            'admin_notes' => $validated['admin_notes'] ?? $application->admin_notes,
            'reviewed_at' => now(),
            'reviewed_by' => $adminId,
        ]);

        Notification::create([
            'model' => 'callingteam',
            'listing_id' => $callingTeamMember->id,
            'job_id' => $application->job_id,
            'type' => 'candidate_assigned_to_calling_team',
            'status' => 'unread',
            'data' => $this->notificationData($application, [
                'admin_notes' => $validated['admin_notes'] ?? null,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Candidate assigned successfully.',
            'data' => $application->fresh(['job', 'candidate', 'assignedCallingTeamMember']),
        ]);
    }

    private function callingTeamMembersForAdmin()
    {
        return Member::query()
            ->where('assigned_admin_id', Auth::guard('admin')->id())
            ->where('is_calling_team', true)
            ->where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'phone', 'username']);
    }

    private function notificationData(JobApplication $application, array $extra = []): array
    {
        $application->loadMissing(['job:id,uuid,title,company,created_by', 'assignedCallingTeamMember:id,name,email']);

        return array_merge([
            'application_id' => $application->id,
            'application_uuid' => $application->uuid,
            'job_id' => $application->job_id,
            'job_uuid' => $application->job?->uuid,
            'job_title' => $application->job?->title,
            'job_company' => $application->job?->company,
            'candidate_id' => $application->candidate_id,
            'candidate_name' => $application->candidate_name,
            'candidate_email' => $application->candidate_email,
            'candidate_phone' => $application->candidate_phone,
            'status' => $application->status,
            'calling_team_member_id' => $application->assigned_calling_team_member_id,
            'calling_team_member_name' => $application->assignedCallingTeamMember?->name,
        ], $extra);
    }

    private function offerLetterViewData(
        JobApplication $application,
        ?SiteSetting $setting,
        string $salaryPackage,
        string $joiningDate
    ): array {
        $logoPath = null;

        if ($setting?->light_logo_path && Storage::disk('public')->exists($setting->light_logo_path)) {
            $logoPath = Storage::disk('public')->path($setting->light_logo_path);
        } elseif ($setting?->dark_logo_path && Storage::disk('public')->exists($setting->dark_logo_path)) {
            $logoPath = Storage::disk('public')->path($setting->dark_logo_path);
        }

        return [
            'application' => $application,
            'setting' => $setting,
            'salaryPackage' => $salaryPackage,
            'joiningDate' => $joiningDate,
            'logoPath' => $logoPath,
            'generatedDate' => now(),
        ];
    }
}
