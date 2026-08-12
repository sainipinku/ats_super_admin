<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectBudget;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProjectApiController extends Controller
{
    public function index(Request $request)
    {
        $query = Project::with(['company', 'client', 'latestBudget']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('current_stage')) {
            $query->where('current_stage', $request->current_stage);
        }

        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        if ($request->has('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('project_code', 'like', "%{$search}%")
                    ->orWhere('project_address', 'like', "%{$search}%");
            });
        }

        $perPage = $request->per_page ?? 15;
        $projects = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $projects->items(),
            'pagination' => [
                'total' => $projects->total(),
                'per_page' => $projects->perPage(),
                'current_page' => $projects->currentPage(),
                'last_page' => $projects->lastPage(),
            ],
        ]);
    }

    public function show(Project $project)
    {
        $project->load([
            'company',
            'client',
            'budgets' => fn ($q) => $q->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
            'surveyPlans',
            'draftingJobs',
            'executionPlans',
            'executionTasks',
            'materials',
            'vehicles',
            'equipments',
            'clientInvoices',
            'clientPayments',
            'handovers',
        ]);

        return response()->json([
            'success' => true,
            'data' => $project,
        ]);
    }

    public function store(Request $request)
    {
        $actor = $request->user('superadmin-api');

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

        return response()->json([
            'success' => true,
            'message' => 'Project created successfully.',
            'data' => $project->load(['company', 'client']),
        ], 201);
    }

    public function update(Request $request, Project $project)
    {
        $validated = $request->validate([
            'company_id' => ['sometimes', 'exists:construction_companies,id'],
            'client_id' => ['sometimes', 'exists:construction_clients,id'],
            'name' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'description' => ['sometimes', 'nullable', 'string'],
            'project_address' => ['sometimes', 'nullable', 'string'],
            'latitude' => ['sometimes', 'nullable', 'numeric'],
            'longitude' => ['sometimes', 'nullable', 'numeric'],
            'start_date' => ['sometimes', 'nullable', 'date'],
            'expected_end_date' => ['sometimes', 'nullable', 'date'],
            'priority' => ['sometimes', 'in:low,medium,high,critical'],
            'status' => ['sometimes', 'in:draft,active,on_hold,completed,cancelled'],
            'current_stage' => ['sometimes', 'string'],
        ]);

        $project->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Project updated successfully.',
            'data' => $project->load(['company', 'client', 'latestBudget']),
        ]);
    }

    public function destroy(Project $project)
    {
        try {
            DB::transaction(function () use ($project) {
                $projectId = $project->id;

                \App\Models\Construction\ActivityLog::where('project_id', $projectId)->delete();
                MemberRoleAssignment::where('project_id', $projectId)->delete();
                ProjectTeamMember::where('project_id', $projectId)->delete();
                ProjectBudget::where('project_id', $projectId)->delete();
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

            return response()->json([
                'success' => true,
                'message' => 'Project and all related data deleted successfully.',
            ]);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete project. ' . $e->getMessage(),
            ], 500);
        }
    }

    public function updateStatus(Request $request, Project $project)
    {
        $validated = $request->validate([
            'status' => ['required', 'in:draft,active,on_hold,completed,cancelled'],
            'current_stage' => ['sometimes', 'nullable', 'string'],
        ]);

        $project->update([
            'status' => $validated['status'],
            'current_stage' => $validated['current_stage'] ?? $project->current_stage,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project status updated successfully.',
            'data' => ['status' => $project->status, 'current_stage' => $project->current_stage],
        ]);
    }

    public function storeBudget(Request $request, Project $project)
    {
        $actor = $request->user('superadmin-api');

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

        return response()->json([
            'success' => true,
            'message' => 'Project budget saved successfully.',
            'data' => $budget,
        ], 201);
    }

    public function budgets(Project $project)
    {
        $budgets = $project->budgets()->latest('version_no')->get();

        return response()->json([
            'success' => true,
            'data' => $budgets,
        ]);
    }

    public function assignTeam(Request $request, Project $project)
    {
        $actor = $request->user('superadmin-api');

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

        if (! empty($validated['role_id'])) {
            MemberRoleAssignment::updateOrCreate([
                'member_id' => $validated['member_id'],
                'role_id' => $validated['role_id'],
                'project_id' => $project->id,
            ]);
        }

        if ($project->current_stage === 'budget_approved') {
            $project->update(['current_stage' => 'team_assigned']);
        }

        return response()->json([
            'success' => true,
            'message' => 'Project team member assigned successfully.',
            'data' => $teamMember->load(['member', 'role']),
        ]);
    }

    public function team(Project $project)
    {
        $team = $project->teamMembers()->with(['member', 'role'])->get();

        return response()->json([
            'success' => true,
            'data' => $team,
        ]);
    }

    public function removeTeamMember(Project $project, $teamMemberId)
    {
        $teamMember = ProjectTeamMember::where('project_id', $project->id)
            ->where('id', $teamMemberId)
            ->first();

        if (! $teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found.',
            ], 404);
        }

        MemberRoleAssignment::where([
            'member_id' => $teamMember->member_id,
            'project_id' => $project->id,
            'role_id' => $teamMember->role_id,
        ])->delete();

        $teamMember->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully.',
        ]);
    }

    public function stats()
    {
        $total = Project::count();
        $draft = Project::where('status', 'draft')->count();
        $active = Project::where('status', 'active')->count();
        $onHold = Project::where('status', 'on_hold')->count();
        $completed = Project::where('status', 'completed')->count();
        $cancelled = Project::where('status', 'cancelled')->count();

        $byStage = Project::selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        $byPriority = Project::selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority')
            ->toArray();

        $totalBudget = ProjectBudget::where('status', 'approved')->sum('approved_amount') ?? 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total' => $total,
                'by_status' => [
                    'draft' => $draft,
                    'active' => $active,
                    'on_hold' => $onHold,
                    'completed' => $completed,
                    'cancelled' => $cancelled,
                ],
                'by_stage' => $byStage,
                'by_priority' => $byPriority,
                'approved_budget_total' => $totalBudget,
            ],
        ]);
    }
}
