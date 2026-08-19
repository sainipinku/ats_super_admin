<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\DrawingApproval;
use App\Models\Construction\Equipment;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\ExecutionPlan;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\Material;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectBudget;
use App\Models\Construction\ProjectHandover;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveySubmission;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleAssignment;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AdminDashboardApiController extends Controller
{
    use ResolvesConstructionActor;

    public function index(Request $request)
    {
        /** @var Member|null $actor */
        $actor = $this->constructionActor();
        $actorId = $actor?->getKey();

        $allProjectsQuery = Project::with(['company', 'client', 'latestBudget']);
        $scopedProjectIds = null;

        if ($actor) {
            $teamProjectIds = ProjectTeamMember::where('member_id', $actorId)
                ->where('status', 'active')
                ->pluck('project_id');

            $companyId = $actor->company_id ?? $request->company_id;
            $clientId = $actor->client_id ?? $request->client_id;

            if ($teamProjectIds->isNotEmpty()) {
                $scopedProjectIds = $teamProjectIds;
                $allProjectsQuery->whereIn('id', $teamProjectIds);
            } elseif ($companyId) {
                $allProjectsQuery->where('company_id', $companyId);
                $scopedProjectIds = Project::where('company_id', $companyId)->pluck('id');
            } elseif ($clientId) {
                $allProjectsQuery->where('client_id', $clientId);
                $scopedProjectIds = Project::where('client_id', $clientId)->pluck('id');
            }
        }

        if ($request->filled('company_id')) {
            $allProjectsQuery->where('company_id', $request->company_id);
            $scopedProjectIds = Project::where('company_id', $request->company_id)->pluck('id');
        }
        if ($request->filled('client_id')) {
            $allProjectsQuery->where('client_id', $request->client_id);
            $scopedProjectIds = Project::where('client_id', $request->client_id)->pluck('id');
        }
        if ($request->filled('status')) {
            $allProjectsQuery->where('status', $request->status);
        }

        $projects = $allProjectsQuery->latest()->get();

        if ($scopedProjectIds === null || $scopedProjectIds->isEmpty()) {
            try {
                $scopedProjectIds = Project::pluck('id');
            } catch (\Throwable $e) {
                report($e);
                $scopedProjectIds = collect([]);
            }
        }
        $projectIds = $projects->pluck('id');

        $companies = collect([]);
        $clients = collect([]);
        try {
            $companies = $this->buildCompanyStats($scopedProjectIds, $request);
            $clients = $this->buildClientStats($scopedProjectIds, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        $stats = [];
        try {
            $stats = $this->buildStats($scopedProjectIds);
        } catch (\Throwable $e) {
            report($e);
        }
        $stats['assigned_projects_direct_count'] = $projects->count();

        $byStage = $projects->countBy('current_stage');
        $byPriority = $projects->countBy('priority');
        $byStatus = [
            'draft' => $projects->where('status', 'draft')->count(),
            'active' => $projects->where('status', 'active')->count(),
            'on_hold' => $projects->where('status', 'on_hold')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'cancelled' => $projects->where('status', 'cancelled')->count(),
        ];

        $recentProjects = $projects->take(10);
        try {
            $recentProjects->load(['company', 'client', 'latestBudget', 'teamMembers.role']);
        } catch (\Throwable $e) {
            report($e);
        }

        $attendanceToday = collect([]);
        try {
            $attendanceToday = AttendanceRecord::whereIn('project_id', $scopedProjectIds->all())
                ->where('attendance_date', now()->toDateString())
                ->latest()
                ->take(50)
                ->get([
                    'id', 'project_id', 'member_id', 'attendance_type',
                    'check_in_at', 'check_out_at', 'hours_worked',
                    'attendance_date', 'status', 'gps_verified',
                ]);
        } catch (\Throwable $e) {
            report($e);
        }

        $recentDprs = collect([]);
        try {
            $recentDprs = DailyProgressReport::with(['project.company', 'submittedBy'])
                ->whereIn('project_id', $scopedProjectIds->all())
                ->latest('report_date')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $pendingApprovals = [
            'survey_submissions' => collect([]),
            'drawing_approvals' => collect([]),
            'dpr_approvals' => collect([]),
            'attendance_approvals' => collect([]),
        ];
        try {
            $pendingApprovals['survey_submissions'] = SurveySubmission::with(['surveyVisit.surveyPlan.project'])
                ->whereIn('project_id', $scopedProjectIds->all())
                ->where('status', 'submitted')
                ->latest('submitted_at')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $pendingApprovals['drawing_approvals'] = DrawingApproval::with(['revision.draftingJob.project'])
                ->whereIn('project_id', $scopedProjectIds->all())
                ->where('decision', 'pending')
                ->latest('requested_at')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $pendingApprovals['dpr_approvals'] = DailyProgressReport::with(['project.company', 'submittedBy'])
                ->whereIn('project_id', $scopedProjectIds->all())
                ->where('status', 'submitted')
                ->latest('report_date')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $pendingApprovals['attendance_approvals'] = AttendanceRecord::with(['project.company', 'member'])
                ->whereIn('project_id', $scopedProjectIds->all())
                ->where('status', 'pending')
                ->latest('attendance_date')
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $teamPerformance = collect([]);
        try {
            $teamPerformance = $this->buildTeamPerformance($scopedProjectIds);
        } catch (\Throwable $e) {
            report($e);
        }

        $finance = [];
        try {
            $finance = $this->buildFinanceSummary($scopedProjectIds);
        } catch (\Throwable $e) {
            report($e);
        }

        $inventory = [];
        try {
            $inventory = $this->buildInventorySummary($scopedProjectIds);
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json([
            'success' => true,
            'actor' => $actor ? [
                'id' => $actor->id,
                'name' => $actor->name,
                'email' => $actor->email,
                'company_id' => $actor->company_id ?? null,
                'client_id' => $actor->client_id ?? null,
                'role_names' => $actor->role_names ?? null,
            ] : null,
            'stats' => $stats,
            'breakdown' => [
                'by_status' => $byStatus,
                'by_stage' => $byStage,
                'by_priority' => $byPriority,
            ],
            'companies' => $companies,
            'clients' => $clients,
            'projects' => [
                'recent' => $recentProjects,
                'total_count' => $projects->count(),
            ],
            'today_attendance' => $attendanceToday,
            'recent_dprs' => $recentDprs,
            'pending_approvals' => $pendingApprovals,
            'team_performance' => $teamPerformance,
            'finance' => $finance,
            'inventory' => $inventory,
        ]);
    }

    private function buildStats($scopedProjectIds)
    {
        $projectIdsArr = $scopedProjectIds->all();

        if (empty($projectIdsArr)) {
            return [
                'total_projects' => 0,
                'active_projects' => 0,
                'completed_projects' => 0,
                'draft_projects' => 0,
                'on_hold_projects' => 0,
                'companies_count' => 0,
                'clients_count' => 0,
                'team_members_count' => 0,
                'survey_plans' => 0,
                'survey_pending_submissions' => 0,
                'drafting_queue' => 0,
                'drawing_approvals_pending' => 0,
                'execution_plans' => 0,
                'execution_tasks' => 0,
                'tasks_completed' => 0,
                'tasks_in_progress' => 0,
                'tasks_blocked' => 0,
                'dpr_pending_review' => 0,
                'attendance_pending_review' => 0,
                'materials_count' => 0,
                'vehicles_count' => 0,
                'equipment_count' => 0,
                'approved_budget_total' => 0,
                'estimated_budget_total' => 0,
            ];
        }

        return [
            'total_projects' => (int) Project::whereIn('id', $projectIdsArr)->count(),
            'active_projects' => (int) Project::whereIn('id', $projectIdsArr)->where('status', 'active')->count(),
            'completed_projects' => (int) Project::whereIn('id', $projectIdsArr)->where('status', 'completed')->count(),
            'draft_projects' => (int) Project::whereIn('id', $projectIdsArr)->where('status', 'draft')->count(),
            'on_hold_projects' => (int) Project::whereIn('id', $projectIdsArr)->where('status', 'on_hold')->count(),
            'companies_count' => (int) Company::whereIn('id', Project::whereIn('id', $projectIdsArr)->pluck('company_id'))->count(),
            'clients_count' => (int) Client::whereIn('id', Project::whereIn('id', $projectIdsArr)->pluck('client_id'))->count(),
            'team_members_count' => (int) ProjectTeamMember::whereIn('project_id', $projectIdsArr)
                ->where('status', 'active')
                ->distinct('member_id')
                ->count('member_id'),
            'survey_plans' => (int) SurveyPlan::whereIn('project_id', $projectIdsArr)->count(),
            'survey_pending_submissions' => (int) SurveySubmission::whereIn('project_id', $projectIdsArr)
                ->where('status', 'submitted')
                ->count(),
            'drafting_queue' => (int) DraftingJob::whereIn('project_id', $projectIdsArr)
                ->whereIn('status', ['queued', 'in_progress', 'submitted'])
                ->count(),
            'drawing_approvals_pending' => (int) DrawingApproval::whereIn('project_id', $projectIdsArr)
                ->where('decision', 'pending')
                ->count(),
            'execution_plans' => (int) ExecutionPlan::whereIn('project_id', $projectIdsArr)->count(),
            'execution_tasks' => (int) ExecutionTask::whereIn('project_id', $projectIdsArr)->count(),
            'tasks_completed' => (int) ExecutionTask::whereIn('id', ExecutionTask::whereIn('project_id', $projectIdsArr)->pluck('id'))->where('status', 'completed')->count(),
            'tasks_in_progress' => (int) ExecutionTask::whereIn('project_id', $projectIdsArr)->where('status', 'in_progress')->count(),
            'tasks_blocked' => (int) ExecutionTask::whereIn('project_id', $projectIdsArr)->where('status', 'blocked')->count(),
            'dpr_pending_review' => (int) DailyProgressReport::whereIn('project_id', $projectIdsArr)
                ->where('status', 'submitted')
                ->count(),
            'attendance_pending_review' => (int) AttendanceRecord::whereIn('project_id', $projectIdsArr)
                ->where('status', 'pending')
                ->count(),
            'materials_count' => (int) Material::whereIn('project_id', $projectIdsArr)->count(),
            'vehicles_count' => (int) Vehicle::whereIn('project_id', $projectIdsArr)->count(),
            'equipment_count' => (int) Equipment::whereIn('project_id', $projectIdsArr)->count(),
            'approved_budget_total' => (float) ProjectBudget::where('status', 'approved')
                ->whereIn('project_id', $projectIdsArr)
                ->sum('approved_amount'),
            'estimated_budget_total' => (float) ProjectBudget::whereIn('project_id', $projectIdsArr)
                ->sum('estimated_amount'),
        ];
    }

    private function buildCompanyStats($scopedProjectIds, Request $request)
    {
        $companyIds = Project::whereIn('id', $scopedProjectIds->all())->pluck('company_id')->unique()->values();

        $query = Company::withCount(['projects' => function ($q) use ($scopedProjectIds) {
            $q->whereIn('id', $scopedProjectIds->all());
        }, 'clients'])
            ->withCount([
                'projects as active_projects_count' => function ($q) use ($scopedProjectIds) {
                    $q->whereIn('id', $scopedProjectIds->all())->where('status', 'active');
                },
                'projects as completed_projects_count' => function ($q) use ($scopedProjectIds) {
                    $q->whereIn('id', $scopedProjectIds->all())->where('status', 'completed');
                },
            ]);

        if ($companyIds->isNotEmpty() && ! $request->filled('company_id')) {
            $query->whereIn('id', $companyIds);
        }
        if ($request->filled('company_id')) {
            $query->where('id', $request->company_id);
        }

        return $query->latest()->limit(20)->get();
    }

    private function buildClientStats($scopedProjectIds, Request $request)
    {
        $clientIds = Project::whereIn('id', $scopedProjectIds->all())->pluck('client_id')->unique()->values();

        $query = Client::withCount(['projects' => function ($q) use ($scopedProjectIds) {
            $q->whereIn('id', $scopedProjectIds->all());
        }])
            ->withCount([
                'projects as active_projects_count' => function ($q) use ($scopedProjectIds) {
                    $q->whereIn('id', $scopedProjectIds->all())->where('status', 'active');
                },
            ])
            ->with('company:id,name');

        if ($clientIds->isNotEmpty() && ! $request->filled('client_id')) {
            $query->whereIn('id', $clientIds);
        }
        if ($request->filled('client_id')) {
            $query->where('id', $request->client_id);
        }

        return $query->latest()->limit(20)->get();
    }

    private function buildTeamPerformance($scopedProjectIds)
    {
        $projectIdsArr = $scopedProjectIds->all();
        if (empty($projectIdsArr)) {
            return collect([]);
        }

        $members = Member::query()
            ->whereIn('id', function ($q) use ($projectIdsArr) {
                $q->select('member_id')
                    ->from('construction_project_team_members')
                    ->whereIn('project_id', $projectIdsArr)
                    ->where('status', 'active');
            })
            ->with([
                'projectTeamMembers' => function ($q) use ($projectIdsArr) {
                    $q->whereIn('project_id', $projectIdsArr)->with(['project:id,name,project_code', 'role:id,name']);
                },
            ])
            ->limit(50)
            ->get();

        $attendanceLast7 = AttendanceRecord::whereIn('project_id', $projectIdsArr)
            ->where('attendance_date', '>=', now()->subDays(7)->toDateString())
            ->get()
            ->groupBy('member_id')
            ->map(fn ($rows) => [
                'days' => $rows->count(),
                'hours' => (float) $rows->sum('hours_worked'),
                'present' => $rows->where('attendance_type', 'present')->count(),
            ]);

        $tasksDoneLast7 = [];
        try {
            $completedTaskIds = ExecutionTask::where('updated_at', '>=', now()->subDays(7))
                ->where('status', 'completed')
                ->pluck('id');

            if ($completedTaskIds->isNotEmpty()) {
                $tasksDoneLast7 = ExecutionTaskAssignee::whereIn('project_id', $projectIdsArr)
                    ->whereIn('execution_task_id', $completedTaskIds)
                    ->where('status', 'active')
                    ->get()
                    ->groupBy('member_id')
                    ->map(fn ($rows) => $rows->count())
                    ->toArray();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return $members->map(function ($m) use ($attendanceLast7, $tasksDoneLast7) {
            $a = $attendanceLast7->get($m->id, ['days' => 0, 'hours' => 0, 'present' => 0]);
            return [
                'member_id' => $m->id,
                'name' => $m->name,
                'phone' => $m->phone,
                'profile_photo_url' => $m->profile_photo_url ?? null,
                'assignments_count' => $m->projectTeamMembers->count(),
                'assignments' => $m->projectTeamMembers->map(fn ($tm) => [
                    'project' => $tm->project?->only(['id', 'name', 'project_code']),
                    'role' => $tm->role?->only(['id', 'name']),
                    'is_primary' => (bool) $tm->is_primary,
                ]),
                'attendance_7d' => $a,
                'tasks_completed_7d' => (int) ($tasksDoneLast7[$m->id] ?? 0),
            ];
        })->values();
    }

    private function buildFinanceSummary($scopedProjectIds)
    {
        $projectIdsArr = $scopedProjectIds->all();
        $empty = [
            'budget' => ['approved_total' => 0, 'estimated_total' => 0],
            'invoices' => ['count' => 0, 'total_amount' => 0, 'paid_amount' => 0, 'balance_due' => 0],
            'payments' => ['count' => 0, 'total_amount' => 0],
            'top_projects_by_budget' => [],
        ];

        if (empty($projectIdsArr)) {
            return $empty;
        }

        try {
            $approvedBudgets = ProjectBudget::where('status', 'approved')
                ->whereIn('project_id', $projectIdsArr)
                ->selectRaw('COALESCE(SUM(approved_amount),0) as approved, COALESCE(SUM(estimated_amount),0) as estimated')
                ->first();
        } catch (\Throwable $e) {
            report($e);
            $approvedBudgets = (object) ['approved' => 0, 'estimated' => 0];
        }

        try {
            $invoices = DB::table('construction_client_invoices')
                ->whereIn('project_id', $projectIdsArr)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(total_amount),0) as total, COALESCE(SUM(amount_paid),0) as paid, COALESCE(SUM(balance_due),0) as balance')
                ->first();
        } catch (\Throwable $e) {
            report($e);
            $invoices = (object) ['count' => 0, 'total' => 0, 'paid' => 0, 'balance' => 0];
        }

        try {
            $payments = DB::table('construction_client_payments')
                ->whereIn('project_id', $projectIdsArr)
                ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount),0) as total')
                ->first();
        } catch (\Throwable $e) {
            report($e);
            $payments = (object) ['count' => 0, 'total' => 0];
        }

        try {
            $budgetsByProject = ProjectBudget::where('status', 'approved')
                ->whereIn('project_id', $projectIdsArr)
                ->join('construction_projects', 'construction_projects.id', '=', 'construction_project_budgets.project_id')
                ->selectRaw('construction_projects.id as project_id, construction_projects.name as project_name, construction_projects.project_code, SUM(construction_project_budgets.approved_amount) as approved_budget')
                ->groupBy(['construction_projects.id', 'construction_projects.name', 'construction_projects.project_code'])
                ->orderByDesc('approved_budget')
                ->limit(10)
                ->get()
                ->all();
        } catch (\Throwable $e) {
            report($e);
            $budgetsByProject = [];
        }

        return [
            'budget' => [
                'approved_total' => (float) ($approvedBudgets->approved ?? 0),
                'estimated_total' => (float) ($approvedBudgets->estimated ?? 0),
            ],
            'invoices' => [
                'count' => (int) ($invoices->count ?? 0),
                'total_amount' => (float) ($invoices->total ?? 0),
                'paid_amount' => (float) ($invoices->paid ?? 0),
                'balance_due' => (float) ($invoices->balance ?? 0),
            ],
            'payments' => [
                'count' => (int) ($payments->count ?? 0),
                'total_amount' => (float) ($payments->total ?? 0),
            ],
            'top_projects_by_budget' => $budgetsByProject,
        ];
    }

    private function buildInventorySummary($scopedProjectIds)
    {
        $projectIdsArr = $scopedProjectIds->all();
        $empty = [
            'materials' => ['count' => 0, 'low_stock_count' => 0],
            'vehicles' => ['total' => 0, 'in_service' => 0, 'maintenance' => 0, 'out_of_service' => 0, 'active_assignments' => []],
            'equipment' => ['total' => 0, 'in_service' => 0, 'maintenance' => 0, 'out_of_service' => 0, 'active_allocations' => []],
            'handovers_count' => 0,
        ];

        if (empty($projectIdsArr)) {
            return $empty;
        }

        $materialCount = 0;
        $lowStock = 0;
        try {
            $materialCount = (int) DB::table('construction_materials')->whereIn('project_id', $projectIdsArr)->count();
        } catch (\Throwable $e) {
            report($e);
        }
        try {
            $stockTable = 'construction_material_stocks';
            $materialTable = 'construction_materials';
            if (Schema::hasColumn($stockTable, 'current_stock') && Schema::hasColumn($stockTable, 'min_stock')) {
                $lowStock = (int) DB::table($stockTable)
                    ->join($materialTable, "{$materialTable}.id", '=', "{$stockTable}.material_id")
                    ->whereIn("{$stockTable}.project_id", $projectIdsArr)
                    ->whereColumn("{$stockTable}.current_stock", '<=', "{$stockTable}.min_stock")
                    ->count();
            } elseif (Schema::hasColumn($stockTable, 'on_hand_quantity')) {
                $lowStock = (int) DB::table($stockTable)
                    ->whereIn("{$stockTable}.project_id", $projectIdsArr)
                    ->where('on_hand_quantity', '<=', 0)
                    ->count();
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $vehicles = (object) ['total' => 0, 'in_service' => 0, 'maintenance' => 0, 'out_of_service' => 0];
        try {
            $vehicleRows = DB::table('construction_vehicles')->whereIn('project_id', $projectIdsArr)->get();
            $vehicles->total = $vehicleRows->count();
            foreach ($vehicleRows as $row) {
                $status = is_numeric($row->status)
                    ? ((int) $row->status === 0 ? 'active' : ((int) $row->status === 1 ? 'inactive' : 'sold'))
                    : strtolower((string) $row->status);
                if ($status === 'active') {
                    $vehicles->in_service++;
                } elseif ($status === 'maintenance') {
                    $vehicles->maintenance++;
                } elseif ($status === 'out_of_service' || $status === 'inactive') {
                    $vehicles->out_of_service++;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $equipment = (object) ['total' => 0, 'in_service' => 0, 'maintenance' => 0, 'out_of_service' => 0];
        try {
            $eqRows = DB::table('construction_equipments')->whereIn('project_id', $projectIdsArr)->get();
            $equipment->total = $eqRows->count();
            foreach ($eqRows as $row) {
                $status = is_numeric($row->status)
                    ? ((int) $row->status === 0 ? 'active' : ((int) $row->status === 1 ? 'inactive' : 'sold'))
                    : strtolower((string) $row->status);
                if ($status === 'active') {
                    $equipment->in_service++;
                } elseif ($status === 'maintenance') {
                    $equipment->maintenance++;
                } elseif ($status === 'out_of_service' || $status === 'inactive') {
                    $equipment->out_of_service++;
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        $activeVehicleAssignments = collect([]);
        try {
            $now = now();
            $activeVehicleAssignments = VehicleAssignment::with(['vehicle', 'driver', 'project:id,name'])
                ->whereIn('project_id', $projectIdsArr)
                ->where('status', 'active')
                ->where(function ($q) use ($now) {
                    $q->whereNull('assigned_to')
                        ->orWhere('assigned_to', '>=', $now);
                })
                ->latest()
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $activeEquipmentAllocations = collect([]);
        try {
            $activeEquipmentAllocations = EquipmentAllocation::with(['equipment', 'assignedTo', 'project:id,name'])
                ->whereIn('project_id', $projectIdsArr)
                ->whereNull('returned_at')
                ->where('status', 'active')
                ->latest()
                ->take(10)
                ->get();
        } catch (\Throwable $e) {
            report($e);
        }

        $handovers = 0;
        try {
            $handovers = (int) ProjectHandover::whereIn('project_id', $projectIdsArr)->count();
        } catch (\Throwable $e) {
            report($e);
        }

        return [
            'materials' => [
                'count' => $materialCount,
                'low_stock_count' => $lowStock,
            ],
            'vehicles' => [
                'total' => (int) ($vehicles->total ?? 0),
                'in_service' => (int) ($vehicles->in_service ?? 0),
                'maintenance' => (int) ($vehicles->maintenance ?? 0),
                'out_of_service' => (int) ($vehicles->out_of_service ?? 0),
                'active_assignments' => $activeVehicleAssignments,
            ],
            'equipment' => [
                'total' => (int) ($equipment->total ?? 0),
                'in_service' => (int) ($equipment->in_service ?? 0),
                'maintenance' => (int) ($equipment->maintenance ?? 0),
                'out_of_service' => (int) ($equipment->out_of_service ?? 0),
                'active_allocations' => $activeEquipmentAllocations,
            ],
            'handovers_count' => $handovers,
        ];
    }
}
