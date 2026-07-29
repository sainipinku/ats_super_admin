<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\ActivityLog;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectBudget;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use App\Models\Member;
use App\Services\Construction\ConstructionActivityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        return Inertia::render('SuperAdmin/Construction/Projects/Index', [
            'projects' => Project::with(['company', 'client', 'latestBudget'])
                ->latest()
                ->get(),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'clients' => Client::orderBy('name')->get(['id', 'name', 'company_id']),
        ]);
    }

    public function show(Project $project): Response
    {
        $project->load([
            'company',
            'client',
            'budgets' => fn ($query) => $query->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans.planMembers.member',
            'surveyPlans.visits.checkedInBy',
            'surveyPlans.visits.entries.capturedBy',
            'surveyPlans.visits.measurements.capturedBy',
            'surveyPlans.visits.submission.submittedBy',
            'surveyPlans.visits.submission.reviewedBy',
            'surveySubmissions.submittedBy',
            'surveySubmissions.reviewedBy',
            'surveySubmissions.surveyVisit.checkedInBy',
            'draftingJobs.assignedTo',
            'draftingJobs.drawingRevisions.uploadedBy',
            'draftingJobs.drawingRevisions.dwgDocument',
            'draftingJobs.drawingRevisions.pdfDocument',
            'draftingJobs.drawingRevisions.approvals.approvedBy',
            'drawingApprovals.drawingRevision',
        ]);

        return Inertia::render('SuperAdmin/Construction/Projects/Show', [
            'project' => $project,
            'members' => Member::orderBy('name')->get(['id', 'name', 'email']),
            'roles' => Role::where('status', 'active')->orderBy('name')->get(['id', 'name', 'slug']),
            'activityLog' => ActivityLog::with('actor')
                ->where('project_id', $project->id)
                ->latest('created_at')
                ->take(15)
                ->get(),
        ]);
    }

    public function store(Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'company_id' => ['required', 'exists:construction_companies,id'],
            'client_id' => ['required', 'exists:construction_clients,id'],
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'project_address' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric'],
            'longitude' => ['nullable', 'numeric'],
            'start_date' => ['nullable', 'date'],
            'expected_end_date' => ['nullable', 'date'],
            'priority' => ['required', 'in:low,medium,high,critical'],
        ]);

        $nextId = (Project::max('id') ?? 0) + 1;
        $nameSlug = Str::slug($validated['name']);

        $project = Project::create([
            ...$validated,
            'project_code' => 'PRJ-' . str_pad((string) $nextId, 5, '0', STR_PAD_LEFT),
            'slug' => $nameSlug . '-' . $nextId,
            'status' => 'draft',
            'current_stage' => 'budget_pending',
            'created_by_type' => $actor ? $actor::class : null,
            'created_by_id' => $actor?->getKey(),
        ]);

        $activityService->log(
            module: 'project',
            action: 'created',
            actor: $actor,
            reference: $project,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['project_code' => $project->project_code],
            request: $request
        );

        return redirect()->route('super.construction.projects.show', $project)
            ->with('success', 'Project created successfully.');
    }

    public function storeBudget(Project $project, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'estimated_amount' => ['required', 'numeric', 'min:0'],
            'approved_amount' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'notes' => ['nullable', 'string'],
            'status' => ['required', 'in:pending,approved,rejected'],
        ]);

        $budget = ProjectBudget::create([
            ...$validated,
            'project_id' => $project->id,
            'version_no' => (int) $project->budgets()->max('version_no') + 1,
            'submitted_by_type' => $actor ? $actor::class : null,
            'submitted_by_id' => $actor?->getKey(),
            'approved_by_type' => $validated['status'] === 'approved' && $actor ? $actor::class : null,
            'approved_by_id' => $validated['status'] === 'approved' ? $actor?->getKey() : null,
            'approved_at' => $validated['status'] === 'approved' ? now() : null,
        ]);

        $project->update([
            'status' => $validated['status'] === 'approved' ? 'active' : $project->status,
            'current_stage' => $validated['status'] === 'approved' ? 'budget_approved' : 'budget_pending',
        ]);

        $activityService->log(
            module: 'project_budget',
            action: $validated['status'],
            actor: $actor,
            reference: $budget,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['version_no' => $budget->version_no],
            request: $request
        );

        return back()->with('success', 'Project budget saved successfully.');
    }

    public function assignTeam(Project $project, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        $validated = $request->validate([
            'member_id' => ['required', 'exists:members,id'],
            'role_id' => ['nullable', 'exists:construction_roles,id'],
            'assigned_from' => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'date'],
            'assignment_scope' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $teamMember = ProjectTeamMember::updateOrCreate(
            [
                'project_id' => $project->id,
                'member_id' => $validated['member_id'],
            ],
            [
                'role_id' => $validated['role_id'] ?? null,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => 'active',
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
            ]
        );

        if (!empty($validated['role_id'])) {
            MemberRoleAssignment::updateOrCreate([
                'member_id' => $validated['member_id'],
                'role_id' => $validated['role_id'],
                'project_id' => $project->id,
            ]);
        }

        if ($project->current_stage === 'budget_approved') {
            $project->update(['current_stage' => 'team_assigned']);
        }

        $activityService->log(
            module: 'project_team',
            action: 'assigned',
            actor: $actor,
            reference: $teamMember,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: ['member_id' => $teamMember->member_id],
            request: $request
        );

        return back()->with('success', 'Project team member assigned successfully.');
    }

    public function destroy(Project $project, Request $request, ConstructionActivityService $activityService): RedirectResponse
    {
        $actor = $this->constructionActor();

        try {
            $projectId = $project->id;
            $companyId = $project->company_id;
            $projectCode = $project->project_code;

            \DB::transaction(function () use ($project) {
                $projectId = $project->id;

                \App\Models\Construction\ActivityLog::where('project_id', $projectId)->delete();
                \App\Models\Construction\MemberRoleAssignment::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectTeamMember::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectBudget::where('project_id', $projectId)->delete();
                \App\Models\Construction\VehicleAssignment::where('project_id', $projectId)->delete();
                \App\Models\Construction\VehicleLocationPing::where('project_id', $projectId)->delete();
                \App\Models\Construction\EquipmentAllocation::where('project_id', $projectId)->delete();
                \App\Models\Construction\EquipmentUsageLog::where('project_id', $projectId)->delete();
                \App\Models\Construction\ProjectHandoverItem::whereIn(
                    'handover_id',
                    \App\Models\Construction\ProjectHandover::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\Construction\ProjectHandover::where('project_id', $projectId)->delete();
                \App\Models\Construction\ClientPayment::where('project_id', $projectId)->delete();
                \App\Models\Construction\ClientInvoice::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialIssue::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialReceipt::where('project_id', $projectId)->delete();
                \App\Models\Construction\MaterialStock::where('project_id', $projectId)->delete();
                \App\Models\Construction\PurchaseOrder::where('project_id', $projectId)->delete();
                \App\Models\Construction\PurchaseRequest::where('project_id', $projectId)->delete();
                \App\Models\Construction\DrawingApproval::where('project_id', $projectId)->delete();
                \App\Models\Construction\DrawingRevision::where('project_id', $projectId)->delete();
                \App\Models\Construction\DraftingJob::where('project_id', $projectId)->delete();
                \App\Models\Construction\Document::where('project_id', $projectId)->delete();
                \App\Models\Construction\AttendanceRecord::where('project_id', $projectId)->delete();
                \App\Models\Construction\DailyProgressItem::whereIn(
                    'daily_progress_report_id',
                    \App\Models\Construction\DailyProgressReport::where('project_id', $projectId)->select('id')
                )->delete();
                \App\Models\Construction\DailyProgressReport::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionTaskAssignee::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionTask::where('project_id', $projectId)->delete();
                \App\Models\Construction\ExecutionPlan::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyMeasurement::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyEntry::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveySubmission::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyVisit::where('project_id', $projectId)->delete();
                \App\Models\Construction\SurveyPlan::where('project_id', $projectId)->delete();

                \App\Models\Construction\Vehicle::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Equipment::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Material::where('project_id', $projectId)->update(['project_id' => null]);
                \App\Models\Construction\Vendor::where('project_id', $projectId)->update(['project_id' => null]);

                $project->delete();
            });

            $activityService->log(
                module: 'project',
                action: 'deleted',
                actor: $actor,
                reference: null,
                companyId: $companyId,
                projectId: null,
                meta: ['project_code' => $projectCode, 'project_id' => $projectId],
                request: $request
            );

            $fallback = route('super.construction.projects.index');
            $intended = redirect()->getIntendedUrl();
            $target = $intended && $intended !== route('super.construction.projects.show', $projectId ?? 0) ? $intended : $fallback;

            return redirect()->to($target)->with('success', 'Project and all related data deleted successfully.');
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'Failed to delete project. ' . $e->getMessage());
        }
    }
}
