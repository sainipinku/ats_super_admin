<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class JobController extends Controller
{
    /**
     * Display job posts page (Inertia)
     */
    public function index()
    {
        return Inertia::render('Admin/JobPosts/Index');
    }

    /**
     * Display job listing page (Inertia)
     */
    public function listing()
    {
        return Inertia::render('Admin/JobPosts/JobListing');
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
            $validated['company_image'] = Storage::url($path);
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
            if ($job->company_image) {
                $oldPath = str_replace('/storage/', '', $job->company_image);
                Storage::disk('public')->delete($oldPath);
            }
            
            $path = $request->file('company_image')->store('job-images', 'public');
            $validated['company_image'] = Storage::url($path);
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
        if ($job->company_image) {
            $oldPath = str_replace('/storage/', '', $job->company_image);
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
}
