<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveyPlanMember;
use App\Models\Construction\SurveySubmission;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SurveyController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        $members = Member::where('status', 1)
            ->orderBy('name')
            ->get(['id', 'name', 'email', 'departments', 'designation']);

        $members->transform(function ($member) {
            $desigStr = '';
            if (!empty($member->designation)) {
                if (is_array($member->designation)) {
                    $desigValues = array_values($member->designation);
                    if (isset($desigValues[0]) && is_numeric($desigValues[0])) {
                        $desigStr = \App\Models\Designation::whereIn('id', $desigValues)->pluck('name')->implode(', ');
                    } else {
                        $desigStr = implode(', ', $desigValues);
                    }
                } else {
                    $desigStr = (string)$member->designation;
                }
            }
            $member->designation_text = $desigStr;
            return $member;
        });

        return Inertia::render('SuperAdmin/Construction/Survey/Index', [
            'surveyPlans' => SurveyPlan::with(['project.company', 'planMembers.member'])
                ->latest()
                ->get(),
            'surveySubmissions' => SurveySubmission::with([
                'project.company',
                'submittedBy',
                'reviewedBy',
                'surveyVisit.checkedInBy',
                'surveyVisit.entries.capturedBy',
                'surveyVisit.entries.supportingDocument',
                'surveyVisit.measurements.capturedBy',
            ])
                ->latest()
                ->get(),
            'projects' => Project::orderBy('name')->get(['id', 'name', 'project_code']),
            'members' => $members,
        ]);
    }

    public function storePlan(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'project_id' => ['required', 'exists:construction_projects,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'site_latitude' => ['nullable', 'numeric'],
            'site_longitude' => ['nullable', 'numeric'],
            'planned_date' => ['nullable', 'date'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time' => ['nullable', 'date_format:H:i'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:members,id'],
        ]);

        $project = Project::findOrFail($validated['project_id']);
        $surveyPlan = SurveyPlan::create([
            ...collect($validated)->except('member_ids')->all(),
            'survey_code' => 'SUR-' . str_pad((string) ((SurveyPlan::max('id') ?? 0) + 1), 5, '0', STR_PAD_LEFT),
            'assigned_by_type' => $actor ? $actor::class : null,
            'assigned_by_id' => $actor?->getKey(),
            'status' => 'planned',
        ]);

        foreach ($validated['member_ids'] ?? [] as $memberId) {
            SurveyPlanMember::updateOrCreate(
                ['survey_plan_id' => $surveyPlan->id, 'member_id' => $memberId],
                ['role_in_survey' => 'surveyor', 'status' => 'assigned']
            );
        }

        $project->update(['current_stage' => 'survey_planned']);

        $activityService->log(
            module: 'survey_plan',
            action: 'created',
            actor: $actor,
            reference: $surveyPlan,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['assigned_members' => $validated['member_ids'] ?? []],
            request: $request
        );

        return back()->with('success', 'Survey plan created successfully.');
    }

    public function updatePlan(SurveyPlan $surveyPlan, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'site_address' => ['nullable', 'string'],
            'site_latitude' => ['nullable', 'numeric'],
            'site_longitude' => ['nullable', 'numeric'],
            'planned_date' => ['nullable', 'date'],
            'planned_start_time' => ['nullable', 'date_format:H:i'],
            'planned_end_time' => ['nullable', 'date_format:H:i'],
            'member_ids' => ['nullable', 'array'],
            'member_ids.*' => ['exists:members,id'],
        ]);

        $project = $surveyPlan->project;

        $surveyPlan->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'site_address' => $validated['site_address'] ?? null,
            'site_latitude' => $validated['site_latitude'] ?? null,
            'site_longitude' => $validated['site_longitude'] ?? null,
            'planned_date' => $validated['planned_date'] ?? null,
            'planned_start_time' => $validated['planned_start_time'] ?? null,
            'planned_end_time' => $validated['planned_end_time'] ?? null,
        ]);

        // Sync the SurveyPlanMember pivot without touching member master data
        // or global/project role assignments.
        $currentMemberIds = $surveyPlan->planMembers()->pluck('member_id')->all();
        $newMemberIds = $validated['member_ids'] ?? [];

        foreach (array_diff($currentMemberIds, $newMemberIds) as $removeId) {
            $surveyPlan->planMembers()->where('member_id', $removeId)->delete();
        }

        foreach ($newMemberIds as $memberId) {
            SurveyPlanMember::updateOrCreate(
                ['survey_plan_id' => $surveyPlan->id, 'member_id' => $memberId],
                ['role_in_survey' => 'surveyor', 'status' => 'assigned']
            );
        }

        $activityService->log(
            module: 'survey_plan',
            action: 'updated',
            actor: $actor,
            reference: $surveyPlan,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['assigned_members' => $newMemberIds],
            request: $request
        );

        return back()->with('success', 'Survey plan updated successfully.');
    }

    public function reviewSubmission(SurveySubmission $submission, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'status' => ['required', 'in:approved,revision_requested,rejected'],
            'review_notes' => ['nullable', 'string'],
        ]);

        $submission->update([
            'status' => $validated['status'],
            'review_notes' => $validated['review_notes'] ?? null,
            'reviewed_by_member_id' => method_exists($actor, 'getKey') && $actor instanceof \App\Models\Member ? $actor->getKey() : null,
            'reviewed_at' => now(),
        ]);

        $project = $submission->project;
        if ($validated['status'] === 'approved') {
            $project->update(['current_stage' => 'drafting_in_progress']);
        }

        $activityService->log(
            module: 'survey_submission',
            action: $validated['status'],
            actor: $actor,
            reference: $submission,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['review_notes' => $validated['review_notes'] ?? null],
            request: $request
        );

        return back()->with('success', 'Survey submission updated successfully.');
    }
}
