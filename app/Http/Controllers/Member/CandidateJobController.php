<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobApplication;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;

class CandidateJobController extends Controller
{
    /**
     * Display job listings for candidates
     */
    public function index(Request $request)
    {
        $query = Job::with(['creator' => function($q) {
                $q->select('id', 'name', 'email');
            }])
            ->where('status', 'active')
            ->where(function($q) {
                $q->whereNull('last_date')
                  ->orWhere('last_date', '>=', now()->format('Y-m-d'));
            })
            ->orderBy('created_at', 'desc');

        // Search by title or company
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('company', 'like', "%{$search}%")
                  ->orWhere('location', 'like', "%{$search}%");
            });
        }

        // Filter by job type
        if ($request->has('job_type') && $request->job_type) {
            $query->where('job_type', $request->job_type);
        }

        // Filter by location
        if ($request->has('location') && $request->location) {
            $query->where('location', 'like', "%{$request->location}%");
        }

        $jobs = $query->paginate(12)->withQueryString();

        // Check which jobs user has already applied to
        $appliedJobIds = [];
        if (Auth::guard('member')->check()) {
            $appliedJobIds = JobApplication::where('candidate_id', Auth::guard('member')->id())
                ->pluck('job_id')
                ->toArray();
        }

        // Get unique locations for filters
        $locations = Job::where('status', 'active')
            ->distinct()
            ->pluck('location');

        // All possible job types from admin form
        $jobTypes = ['Full Time', 'Part Time', 'Contract', 'Internship', 'Freelance', 'Remote'];

        return Inertia::render('Member/JobListings', [
            'jobs' => $jobs,
            'appliedJobIds' => $appliedJobIds,
            'filters' => [
                'search' => $request->search ?? '',
                'job_type' => $request->job_type ?? '',
                'location' => $request->location ?? '',
            ],
            'jobTypes' => $jobTypes,
            'locations' => $locations,
        ]);
    }

    /**
     * Get single job details
     */
    public function show(Job $job)
    {
        if ($job->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This job is not available.',
            ], 404);
        }

        $job->load(['creator' => function($q) {
            $q->select('id', 'name');
        }]);

        // Check if user has already applied
        $hasApplied = false;
        $application = null;
        
        if (Auth::guard('member')->check()) {
            $application = JobApplication::where('job_id', $job->id)
                ->where('candidate_id', Auth::guard('member')->id())
                ->first();
            $hasApplied = !is_null($application);
        }

        return response()->json([
            'success' => true,
            'job' => [
                'id' => $job->id,
                'uuid' => $job->uuid,
                'title' => $job->title,
                'company' => $job->company,
                'description' => $job->description,
                'location' => $job->location,
                'job_type' => $job->job_type,
                'experience' => $job->experience,
                'salary' => $job->salary,
                'skills' => $job->skills,
                'perks' => $job->perks,
                'key_responsibilities' => $job->key_responsibilities,
                'qualifications' => $job->qualifications,
                'last_date' => $job->last_date,
                'company_image' => $job->company_image,
                'applicants' => $job->applicants,
                'status' => $job->status,
                'created_at' => $job->created_at,
                'creator' => $job->creator,
            ],
            'hasApplied' => $hasApplied,
            'application' => $application,
        ]);
    }

    /**
     * Submit job application
     */
    public function apply(Request $request, Job $job)
    {
        if ($job->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This job is no longer accepting applications.',
            ], 400);
        }

        $candidate = Auth::guard('member')->user();

        // Check if already applied
        $existingApplication = JobApplication::where('job_id', $job->id)
            ->where('candidate_id', $candidate->id)
            ->first();

        if ($existingApplication) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied for this job.',
            ], 400);
        }

        $validated = $request->validate([
            'cover_letter' => 'nullable|string|max:5000',
            'resume' => 'nullable|file|mimes:pdf,doc,docx|max:5120', // 5MB max
        ]);

        // Handle resume upload
        $resumeUrl = null;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $filename = 'resumes/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $resumeUrl = $file->storeAs('public', $filename);
            $resumeUrl = str_replace('public/', 'storage/', $resumeUrl);
        }

        // Create application
        $application = JobApplication::create([
            'uuid' => (string) Str::uuid(),
            'job_id' => $job->id,
            'candidate_id' => $candidate->id,
            'cover_letter' => $validated['cover_letter'] ?? null,
            'resume_url' => $resumeUrl,
            'candidate_name' => $candidate->name,
            'candidate_email' => $candidate->email,
            'candidate_phone' => $candidate->phone,
            'candidate_skills' => $candidate->skills ?? null,
            'candidate_experience' => $candidate->experience ?? null,
            'status' => 'pending',
        ]);

        // Increment job applicants count
        $job->increment('applicants');

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully!',
            'application' => $application,
        ]);
    }

    /**
     * Get candidate's applications
     */
    public function myApplications(Request $request)
    {
        $candidate = Auth::guard('member')->user();

        $applications = JobApplication::with(['job' => function($q) {
                $q->select('id', 'title', 'company', 'location', 'job_type', 'salary', 'status as job_status');
            }])
            ->where('candidate_id', $candidate->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        // Application status counts
        $statusCounts = [
            'pending' => JobApplication::where('candidate_id', $candidate->id)->where('status', 'pending')->count(),
            'reviewing' => JobApplication::where('candidate_id', $candidate->id)->where('status', 'reviewing')->count(),
            'shortlisted' => JobApplication::where('candidate_id', $candidate->id)->where('status', 'shortlisted')->count(),
            'rejected' => JobApplication::where('candidate_id', $candidate->id)->where('status', 'rejected')->count(),
            'hired' => JobApplication::where('candidate_id', $candidate->id)->where('status', 'hired')->count(),
        ];

        return Inertia::render('Member/MyApplications', [
            'applications' => $applications,
            'statusCounts' => $statusCounts,
        ]);
    }

    /**
     * Withdraw application
     */
    public function withdraw(JobApplication $application)
    {
        $candidate = Auth::guard('member')->user();

        if ($application->candidate_id !== $candidate->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized action.',
            ], 403);
        }

        if (!in_array($application->status, ['pending', 'reviewing'])) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot withdraw application at this stage.',
            ], 400);
        }

        $application->delete();

        // Decrement job applicants count
        Job::where('id', $application->job_id)->decrement('applicants');

        return response()->json([
            'success' => true,
            'message' => 'Application withdrawn successfully.',
        ]);
    }
}
