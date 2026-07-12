<?php

namespace App\Http\Controllers\Admin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveySubmission;
use App\Models\Member;
use Inertia\Inertia;
use Inertia\Response;



class DashboardController extends Controller
{
    use ResolvesConstructionActor;




    public function index(): Response
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $projectIds = ProjectTeamMember::where('member_id', $actor?->getKey())->pluck('project_id');

        return Inertia::render('Admin/Construction/Dashboard', [
            'stats' => [
                'assignedProjects' => $projectIds->count(),
                'surveyPlans' => SurveyPlan::whereIn('project_id', $projectIds)->count(),
                'surveyApprovalsPending' => SurveySubmission::whereIn('project_id', $projectIds)
                    ->where('status', 'submitted')
                    ->count(),
                'draftingQueue' => DraftingJob::whereIn('project_id', $projectIds)
                    ->whereIn('status', ['queued', 'in_progress', 'submitted'])
                    ->count(),
            ],
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->whereIn('id', $projectIds)
                ->latest()
                ->get(),
        ]);
    }
}
