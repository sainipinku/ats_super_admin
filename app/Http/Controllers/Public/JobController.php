<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Job;
use Illuminate\Http\Request;
use Inertia\Inertia;

class JobController extends Controller
{
    /**
     * Display a listing of jobs for public viewing.
     */
    public function index(Request $request)
    {
        $jobs = Job::with(['creator'])
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();

        return Inertia::render('Public/JobListings', [
            'jobs' => $jobs,
            'auth' => [
                'user' => auth()->user(),
            ],
        ]);
    }

    /**
     * Display the specified job.
     */
    public function show(Job $job)
    {
        if ($job->status !== 'active') {
            abort(404);
        }

        $job->load(['creator']);

        return Inertia::render('Public/JobDetails', [
            'job' => $job,
            'auth' => [
                'user' => auth()->user(),
            ],
        ]);
    }
}
