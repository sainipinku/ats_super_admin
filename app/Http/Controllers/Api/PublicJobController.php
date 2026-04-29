<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;

class PublicJobController extends Controller
{
    /**
     * Get active jobs for public display
     */
    public function index(Request $request)
    {
        try {
            $query = Job::where('status', 'active')
                ->where(function ($q) {
                    $q->whereNull('last_date')
                      ->orWhere('last_date', '>=', now());
                });

            // Search by keyword
            if ($request->has('q')) {
                $search = $request->input('q');
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            // Filter by location
            if ($request->has('location')) {
                $location = $request->input('location');
                $query->where('location', 'like', "%{$location}%");
            }

            // Filter by job type
            if ($request->has('type')) {
                $query->where('job_type', $request->input('type'));
            }

            // Order by newest first
            $query->orderBy('created_at', 'desc');

            // Paginate if requested, otherwise get all
            if ($request->has('per_page')) {
                $jobs = $query->paginate($request->input('per_page', 10));
            } else {
                $jobs = $query->limit($request->input('limit', 20))->get();
            }

            // Transform the jobs for frontend
            $transformedJobs = $jobs->map(function ($job) {
                return [
                    'id' => $job->uuid ?? $job->id,
                    'title' => $job->title,
                    'company_name' => $job->company,
                    'company_logo' => $job->company_image,
                    'location' => $job->location,
                    'salary' => $job->salary,
                    'job_type' => $job->job_type,
                    'experience_level' => $job->experience,
                    'is_remote' => $this->isRemoteJob($job->location),
                    'created_at' => $job->created_at,
                    'last_date' => $job->last_date,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $transformedJobs,
                'count' => $transformedJobs->count(),
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch jobs',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get a single job by ID/UUID
     */
    public function show($id)
    {
        try {
            $job = Job::where(function ($q) use ($id) {
                $q->where('uuid', $id)
                  ->orWhere('id', $id);
            })
            ->where('status', 'active')
            ->first();

            if (!$job) {
                return response()->json([
                    'success' => false,
                    'message' => 'Job not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $job->uuid ?? $job->id,
                    'title' => $job->title,
                    'company_name' => $job->company,
                    'company_logo' => $job->company_image,
                    'description' => $job->description,
                    'location' => $job->location,
                    'salary' => $job->salary,
                    'job_type' => $job->job_type,
                    'experience_level' => $job->experience,
                    'skills' => $job->skills ?? [],
                    'requirements' => $job->requirements ?? [],
                    'responsibilities' => $job->responsibilities ?? [],
                    'perks' => $job->perks ?? [],
                    'is_remote' => $this->isRemoteJob($job->location),
                    'created_at' => $job->created_at,
                    'last_date' => $job->last_date,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch job',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Check if job is remote based on location
     */
    private function isRemoteJob($location)
    {
        if (empty($location)) {
            return true;
        }
        
        $remoteKeywords = ['remote', 'work from home', 'wfh', 'anywhere', 'virtual'];
        $locationLower = strtolower($location);
        
        foreach ($remoteKeywords as $keyword) {
            if (str_contains($locationLower, $keyword)) {
                return true;
            }
        }
        
        return false;
    }
}
