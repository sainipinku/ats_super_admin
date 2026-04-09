<?php

namespace App\Http\Controllers\Api\Member;

use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Models\JobApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class JobController extends Controller
{
    public function index(Request $request)
    {
        $query = Job::query()
            ->with(['creator' => function ($q) {
                $q->select('id', 'name', 'email');
            }])
            ->where('status', 'active')
            ->where(function ($q) {
                $q->whereNull('last_date')
                    ->orWhere('last_date', '>=', now()->format('Y-m-d'));
            })
            ->orderByDesc('created_at');

        if ($request->filled('search')) {
            $search = $request->string('search')->toString();
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('company', 'like', "%{$search}%")
                    ->orWhere('location', 'like', "%{$search}%");
            });
        }

        if ($request->filled('job_type')) {
            $query->where('job_type', $request->string('job_type')->toString());
        }

        if ($request->filled('location')) {
            $location = $request->string('location')->toString();
            $query->where('location', 'like', "%{$location}%");
        }

        $perPage = (int) ($request->input('per_page', 12));
        $jobs = $query->paginate(max(1, min($perPage, 50)))->withQueryString();

        $member = $request->user();
        $appliedJobIds = JobApplication::query()
            ->where('candidate_id', $member->id)
            ->pluck('job_id')
            ->toArray();

        return response()->json([
            'success' => true,
            'jobs' => $jobs,
            'applied_job_ids' => $appliedJobIds,
        ]);
    }

    public function show(Request $request, Job $job)
    {
        if ($job->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This job is not available.',
            ], 404);
        }

        $job->load(['creator' => function ($q) {
            $q->select('id', 'name');
        }]);

        $member = $request->user();
        $application = JobApplication::query()
            ->where('job_id', $job->id)
            ->where('candidate_id', $member->id)
            ->first();

        return response()->json([
            'success' => true,
            'job' => $job,
            'has_applied' => (bool) $application,
            'application' => $application,
        ]);
    }

    public function apply(Request $request, Job $job)
    {
        if ($job->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This job is no longer accepting applications.',
            ], 400);
        }

        $member = $request->user();

        $existing = JobApplication::query()
            ->where('job_id', $job->id)
            ->where('candidate_id', $member->id)
            ->exists();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You have already applied for this job.',
            ], 400);
        }

        $validated = $request->validate([
            'cover_letter' => ['nullable', 'string', 'max:5000'],
            'resume' => ['nullable', 'file', 'mimes:pdf,doc,docx', 'max:5120'],
        ]);

        $resumeUrl = null;
        if ($request->hasFile('resume')) {
            $file = $request->file('resume');
            $filename = 'resumes/' . Str::uuid() . '.' . $file->getClientOriginalExtension();
            $stored = $file->storeAs('public', $filename);
            $resumeUrl = str_replace('public/', 'storage/', $stored);
        }

        $application = JobApplication::create([
            'uuid' => (string) Str::uuid(),
            'job_id' => $job->id,
            'candidate_id' => $member->id,
            'cover_letter' => $validated['cover_letter'] ?? null,
            'resume_url' => $resumeUrl,
            'candidate_name' => $member->name,
            'candidate_email' => $member->email,
            'candidate_phone' => $member->phone,
            'candidate_skills' => $member->skills ?? null,
            'candidate_experience' => $member->experience ?? null,
            'status' => 'pending',
        ]);

        if (isset($job->applicants)) {
            $job->increment('applicants');
        }

        return response()->json([
            'success' => true,
            'message' => 'Application submitted successfully!',
            'application' => $application,
        ]);
    }

    public function myApplications(Request $request)
    {
        $member = $request->user();

        $perPage = (int) ($request->input('per_page', 10));

        $applications = JobApplication::query()
            ->with(['job' => function ($q) {
                $q->select('id', 'title', 'company', 'location', 'job_type', 'salary', 'status as job_status');
            }])
            ->where('candidate_id', $member->id)
            ->orderByDesc('created_at')
            ->paginate(max(1, min($perPage, 50)));

        $statusCounts = [
            'pending' => JobApplication::where('candidate_id', $member->id)->where('status', 'pending')->count(),
            'reviewing' => JobApplication::where('candidate_id', $member->id)->where('status', 'reviewing')->count(),
            'shortlisted' => JobApplication::where('candidate_id', $member->id)->where('status', 'shortlisted')->count(),
            'rejected' => JobApplication::where('candidate_id', $member->id)->where('status', 'rejected')->count(),
            'hired' => JobApplication::where('candidate_id', $member->id)->where('status', 'hired')->count(),
        ];

        return response()->json([
            'success' => true,
            'applications' => $applications,
            'status_counts' => $statusCounts,
        ]);
    }

    public function withdraw(Request $request, JobApplication $application)
    {
        $member = $request->user();

        if ((int) $application->candidate_id !== (int) $member->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.',
            ], 403);
        }

        $application->delete();

        return response()->json([
            'success' => true,
            'message' => 'Application withdrawn.',
        ]);
    }
}
