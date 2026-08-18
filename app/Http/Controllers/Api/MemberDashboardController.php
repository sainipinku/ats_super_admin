<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\Client;
use App\Models\Construction\Company;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\ExecutionTaskAssignee;
use App\Models\Construction\Material;
use App\Models\Construction\MaterialStock;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectBudget;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveyPlanMember;
use App\Models\Construction\SurveyVisit;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MemberDashboardController extends Controller
{
    public function index(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $teamProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

        $surveyProjectIds = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->pluck('project_id');

        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

        $projectIds = $teamProjectIds
            ->merge($surveyProjectIds)
            ->merge($taskProjectIds)
            ->unique()
            ->values();

        $companyIds = Project::whereIn('id', $projectIds)->pluck('company_id')->unique()->values();

        $companies = Company::withCount(['projects', 'clients'])
            ->whereIn('id', $companyIds)
            ->orderBy('name')
            ->get(['id', 'name', 'email','legal_name','phone','logo_path','status']);

        $projects = Project::with(['company', 'client', 'latestBudget', 'teamMembers.role'])
            ->whereIn('id', $projectIds)
            ->latest()
            ->get();

        $primaryProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('is_primary', true)
            ->where('status', 'active')
            ->pluck('project_id');

        $primaryProjects = Project::with(['company', 'client', 'latestBudget'])
            ->whereIn('id', $primaryProjectIds)
            ->get();

        // Only survey plans where the authenticated member is explicitly assigned
        // should appear in the member dashboard survey workspace.
        // The broader project scope must NOT expose plans the member was not assigned to.
        $surveyPlans = SurveyPlan::with(['project.company', 'project.client', 'planMembers.member'])
            ->whereHas('planMembers', function ($q) use ($memberId) {
                $q->where('member_id', $memberId);
            })
            ->latest()
            ->get();

        $today = now()->toDateString();

        $todayVisits = SurveyVisit::with(['surveyPlan', 'project'])
            ->whereHas('surveyPlan.planMembers', function ($q) use ($memberId) {
                $q->where('member_id', $memberId);
            })
            ->whereDate('check_in_at', $today)
            ->latest()
            ->get();

        $pendingSurveyPlans = SurveyPlan::with(['project'])
            ->whereHas('planMembers', function ($q) use ($memberId) {
                $q->where('member_id', $memberId);
            })
            ->whereIn('status', ['pending', 'in_progress'])
            ->count();

        $tasks = ExecutionTask::with(['project', 'executionPlan', 'supervisor'])
            ->whereHas('assignees', function ($q) use ($memberId) {
                $q->where('member_id', $memberId)->where('status', 'active');
            })
            ->latest()
            ->get();

        $taskCounts = [
            'total' => $tasks->count(),
            'pending' => $tasks->where('status', 'planned')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
        ];

        $todayAttendance = AttendanceRecord::where('member_id', $memberId)
            ->where('attendance_date', $today)
            ->latest()
            ->first();

        $attendanceLast30 = AttendanceRecord::where('member_id', $memberId)
            ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
            ->latest('attendance_date')
            ->get();

        $attendanceSummary = [
            'total_days' => $attendanceLast30->count(),
            'present' => $attendanceLast30->where('attendance_type', 'present')->count(),
            'half_day' => $attendanceLast30->where('attendance_type', 'half_day')->count(),
            'overtime' => $attendanceLast30->where('attendance_type', 'overtime')->count(),
            'check_in_today' => (bool) $todayAttendance,
            'today_record' => $todayAttendance,
        ];

        $draftingJobs = DraftingJob::with(['project'])
            ->where('assigned_to_member_id', $memberId)
            ->latest()
            ->get();

        $recentDPRs = DailyProgressReport::with(['project'])
            ->where('submitted_by_member_id', $memberId)
            ->latest('report_date')
            ->take(10)
            ->get();

        $vehiclesAssigned = VehicleAssignment::with(['vehicle', 'project'])
            ->where('assigned_to_member_id', $memberId)
            ->whereNull('returned_at')
            ->latest()
            ->get();

        $equipmentAssigned = EquipmentAllocation::with(['equipment', 'project'])
            ->where('assigned_to_member_id', $memberId)
            ->whereNull('returned_at')
            ->latest()
            ->get();

        $projectStageCounts = $projects->countBy('current_stage');

        $projectStatusCounts = [
            'draft' => $projects->where('status', 'draft')->count(),
            'active' => $projects->where('status', 'active')->count(),
            'on_hold' => $projects->where('status', 'on_hold')->count(),
            'completed' => $projects->where('status', 'completed')->count(),
            'cancelled' => $projects->where('status', 'cancelled')->count(),
            'total' => $projects->count(),
        ];

        $totalBudgetApproved = ProjectBudget::where('status', 'approved')
            ->whereIn('project_id', $projectIds->all())
            ->sum('approved_amount') ?? 0;

        return response()->json([
            'success' => true,
            'member' => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'phone' => $member->phone,
                'role_names' => $member->role_names ?? null,
                'profile_photo_url' => $member->profile_photo_url ?? null,
            ],
            'summary' => [
                'companies_count' => $companies->count(),
                'projects_count' => $projects->count(),
                'primary_projects_count' => $primaryProjects->count(),
                'survey_plans_count' => $surveyPlans->count(),
                'pending_surveys' => $pendingSurveyPlans,
                'tasks_count' => $taskCounts,
                'attendance' => $attendanceSummary,
                'drafting_count' => $draftingJobs->count(),
                'vehicles_assigned_count' => $vehiclesAssigned->count(),
                'equipment_assigned_count' => $equipmentAssigned->count(),
                'total_approved_budget' => $totalBudgetApproved,
            ],
            'companies' => $companies,
            'projects' => $projects,
            'primary_projects' => $primaryProjects,
            'projects_by_status' => $projectStatusCounts,
            'projects_by_stage' => $projectStageCounts,
            'survey_plans' => $surveyPlans,
            'today_survey_visits' => $todayVisits,
            'tasks' => $tasks,
            'task_summary' => $taskCounts,
            'drafting_jobs' => $draftingJobs,
            'recent_dprs' => $recentDPRs,
            'vehicles_assigned' => $vehiclesAssigned,
            'equipment_assigned' => $equipmentAssigned,
            'quick_links' => [
                'assigned_projects' => '/api/member/dashboard/projects',
                'today_attendance' => '/api/member/dashboard/attendance',
                'assigned_tasks' => '/api/construction/mobile/construction/tasks/assigned',
                'survey_plans' => '/api/member/dashboard/surveys',
            ],
        ]);
    }

    public function myProjects(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $teamProjectIds = ProjectTeamMember::where('member_id', $memberId)
            ->where('status', 'active')
            ->pluck('project_id');

        $surveyProjectIds = SurveyPlanMember::where('member_id', $memberId)->pluck('project_id');
        $taskProjectIds = ExecutionTaskAssignee::where('member_id', $memberId)->where('status', 'active')->pluck('project_id');

        $projectIds = $teamProjectIds->merge($surveyProjectIds)->merge($taskProjectIds)->unique()->values();

        $query = Project::with([
            'company',
            'client',
            'latestBudget',
            'teamMembers.role',
            'surveyPlans' => function ($q) {
                $q->latest()->take(5);
            },
            'executionTasks' => function ($q) use ($memberId) {
                $q->whereHas('assignees', function ($sub) use ($memberId) {
                    $sub->where('member_id', $memberId);
                })->latest()->take(5);
            },
        ])->whereIn('id', $projectIds);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('company_id')) {
            $query->where('company_id', $request->company_id);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }
        if ($request->filled('current_stage')) {
            $query->where('current_stage', $request->current_stage);
        }
        if ($request->filled('search')) {
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

    public function mySurveys(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $query = SurveyPlan::with([
            'project.company',
            'project.client',
            'planMembers.member',
            'visits' => function ($q) {
                $q->latest()->take(5);
            },
        ])->whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        });

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('planned_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('planned_date', '<=', $request->to_date);
        }

        $perPage = $request->per_page ?? 15;
        $surveys = $query->latest()->paginate($perPage);

        $pending = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->whereIn('status', ['pending', 'in_progress'])->count();

        $completed = SurveyPlan::whereHas('planMembers', function ($q) use ($memberId) {
            $q->where('member_id', $memberId);
        })->where('status', 'submitted')->count();

        return response()->json([
            'success' => true,
            'summary' => [
                'total' => $surveys->total(),
                'pending_inprogress' => $pending,
                'completed' => $completed,
            ],
            'data' => $surveys->items(),
            'pagination' => [
                'total' => $surveys->total(),
                'per_page' => $surveys->perPage(),
                'current_page' => $surveys->currentPage(),
                'last_page' => $surveys->lastPage(),
            ],
        ]);
    }

    public function myTasks(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $query = ExecutionTask::with([
            'project.company',
            'project.client',
            'executionPlan',
            'supervisor',
        ])->whereHas('assignees', function ($q) use ($memberId) {
            $q->where('member_id', $memberId)->where('status', 'active');
        });

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('priority')) {
            $query->where('priority', $request->priority);
        }

        $perPage = $request->per_page ?? 15;
        $tasks = $query->latest()->paginate($perPage);

        $summary = DB::table('construction_execution_task_assignees')
            ->join('construction_execution_tasks', 'construction_execution_task_assignees.execution_task_id', '=', 'construction_execution_tasks.id')
            ->where('construction_execution_task_assignees.member_id', $memberId)
            ->where('construction_execution_task_assignees.status', 'active')
            ->selectRaw('construction_execution_tasks.status, COUNT(*) as count')
            ->groupBy('construction_execution_tasks.status')
            ->pluck('count', 'status')
            ->toArray();

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $tasks->items(),
            'pagination' => [
                'total' => $tasks->total(),
                'per_page' => $tasks->perPage(),
                'current_page' => $tasks->currentPage(),
                'last_page' => $tasks->lastPage(),
            ],
        ]);
    }

    public function myAttendance(Request $request)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $query = AttendanceRecord::with(['project', 'executionTask'])
            ->where('member_id', $memberId);

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('attendance_date', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('attendance_date', '<=', $request->to_date);
        }
        if ($request->filled('attendance_type')) {
            $query->where('attendance_type', $request->attendance_type);
        }

        $perPage = $request->per_page ?? 30;
        $records = $query->latest('attendance_date')->paginate($perPage);

        $today = now()->toDateString();
        $todayRecord = AttendanceRecord::where('member_id', $memberId)
            ->where('attendance_date', $today)
            ->latest()
            ->first();

        $last30 = AttendanceRecord::where('member_id', $memberId)
            ->where('attendance_date', '>=', now()->subDays(30)->toDateString())
            ->get();

        $summary = [
            'today' => [
                'checked_in' => (bool) $todayRecord,
                'record' => $todayRecord,
            ],
            'last_30_days' => [
                'total_records' => $last30->count(),
                'present' => $last30->where('attendance_type', 'present')->count(),
                'half_day' => $last30->where('attendance_type', 'half_day')->count(),
                'overtime' => $last30->where('attendance_type', 'overtime')->count(),
                'total_hours' => (float) $last30->sum('hours_worked'),
            ],
        ];

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'data' => $records->items(),
            'pagination' => [
                'total' => $records->total(),
                'per_page' => $records->perPage(),
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
            ],
        ]);
    }

    public function projectDetail(Request $request, Project $project)
    {
        $member = $request->user();
        $memberId = $member->getKey();

        $isAssigned = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();

        $isSurveyMember = SurveyPlanMember::where('member_id', $memberId)
            ->whereIn('survey_plan_id', SurveyPlan::where('project_id', $project->id)->pluck('id'))
            ->exists();

        $isTaskAssignee = ExecutionTaskAssignee::where('member_id', $memberId)
            ->where('status', 'active')
            ->whereIn('execution_task_id', ExecutionTask::where('project_id', $project->id)->pluck('id'))
            ->exists();

        if (! ($isAssigned || $isSurveyMember || $isTaskAssignee)) {
            return response()->json([
                'success' => false,
                'message' => 'You are not assigned to this project.',
            ], 403);
        }

        $teamRole = ProjectTeamMember::where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->with('role')
            ->first();

        $project->load([
            'company',
            'client',
            'budgets' => fn ($q) => $q->latest('version_no'),
            'teamMembers.member',
            'teamMembers.role',
        ]);

        $surveyPlans = SurveyPlan::with([
            'planMembers.member',
            'visits.checkedInBy',
            'visits.entries.capturedBy',
            'visits.measurements.capturedBy',
            'visits.submission',
        ])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $mySurveyPlans = $surveyPlans->filter(function ($plan) use ($memberId) {
            return $plan->planMembers->contains('member_id', $memberId);
        })->values();

        $tasks = ExecutionTask::with(['executionPlan', 'supervisor', 'assignees.member'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $myTasks = $tasks->filter(function ($task) use ($memberId) {
            return $task->assignees->contains(fn ($a) => $a->member_id == $memberId && $a->status === 'active');
        })->values();

        $draftingJobs = DraftingJob::with(['assignedTo', 'drawingRevisions.uploadedBy'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $myDraftingJobs = $draftingJobs->where('assigned_to_member_id', $memberId)->values();

        $materials = Material::with('stocks')
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $stocks = MaterialStock::with('material')
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $vehicles = Vehicle::with(['assignments' => fn ($q) => $q->latest()->take(3)])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $myVehicles = $vehicles->filter(function ($v) use ($memberId) {
            return $v->assignments->contains(fn ($a) => $a->assigned_to_member_id == $memberId && is_null($a->returned_at));
        })->values();

        $equipments = \App\Models\Construction\Equipment::with([
            'allocations' => fn ($q) => $q->latest()->take(3),
        ])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $myEquipments = $equipments->filter(function ($e) use ($memberId) {
            return $e->allocations->contains(fn ($a) => $a->assigned_to_member_id == $memberId && is_null($a->returned_at));
        })->values();

        $invoices = \App\Models\Construction\ClientInvoice::with(['items', 'payments'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $payments = \App\Models\Construction\ClientPayment::where('project_id', $project->id)
            ->latest()
            ->get();

        $handovers = \App\Models\Construction\ProjectHandover::with(['items', 'finalDocument'])
            ->where('project_id', $project->id)
            ->latest()
            ->get();

        $dprs = DailyProgressReport::with(['items', 'submittedBy'])
            ->where('project_id', $project->id)
            ->latest('report_date')
            ->take(20)
            ->get();

        $attendance = AttendanceRecord::with(['member'])
            ->where('project_id', $project->id)
            ->latest('attendance_date')
            ->take(50)
            ->get();

        $myAttendance = $attendance->where('member_id', $memberId)->values();

        $taskSummary = [
            'total' => $tasks->count(),
            'planned' => $tasks->where('status', 'planned')->count(),
            'in_progress' => $tasks->where('status', 'in_progress')->count(),
            'completed' => $tasks->where('status', 'completed')->count(),
            'blocked' => $tasks->where('status', 'blocked')->count(),
            'assigned_to_me' => $myTasks->count(),
        ];

        $surveySummary = [
            'total' => $surveyPlans->count(),
            'pending' => $surveyPlans->where('status', 'pending')->count(),
            'in_progress' => $surveyPlans->where('status', 'in_progress')->count(),
            'submitted' => $surveyPlans->where('status', 'submitted')->count(),
            'assigned_to_me' => $mySurveyPlans->count(),
        ];

        $financeSummary = [
            'budget_total_invoices' => $invoices->sum('total_amount') ?? 0,
            'paid_total' => $payments->sum('amount') ?? 0,
            'pending' => ($invoices->sum('total_amount') ?? 0) - ($payments->sum('amount') ?? 0),
            'invoices_count' => $invoices->count(),
            'payments_count' => $payments->count(),
        ];

        return response()->json([
            'success' => true,
            'my_assignment' => [
                'role_in_project' => $teamRole,
                'is_team_member' => $isAssigned,
                'is_survey_member' => $isSurveyMember,
                'is_task_assignee' => $isTaskAssignee,
            ],
            'project' => $project,
            'summary' => [
                'tasks' => $taskSummary,
                'surveys' => $surveySummary,
                'finance' => $financeSummary,
                'materials_count' => $materials->count(),
                'vehicles_count' => $vehicles->count(),
                'equipment_count' => $equipments->count(),
                'dpr_count' => $dprs->count(),
            ],
            'modules' => [
                'survey_plans' => $surveyPlans,
                'my_survey_plans' => $mySurveyPlans,
                'tasks' => $tasks,
                'my_tasks' => $myTasks,
                'drafting_jobs' => $draftingJobs,
                'my_drafting_jobs' => $myDraftingJobs,
                'materials' => $materials,
                'material_stocks' => $stocks,
                'vehicles' => $vehicles,
                'my_vehicles' => $myVehicles,
                'equipment' => $equipments,
                'my_equipment' => $myEquipments,
                'invoices' => $invoices,
                'payments' => $payments,
                'handovers' => $handovers,
                'daily_progress' => $dprs,
                'attendance' => $attendance,
                'my_attendance' => $myAttendance,
            ],
        ]);
    }
}
