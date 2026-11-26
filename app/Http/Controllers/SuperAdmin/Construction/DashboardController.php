<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\ConstructionActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Client;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;
use App\Models\Company;
use App\Models\DailyProgressReport;
use App\Models\DraftingJob;
use App\Models\ConstructionEquipment;
use App\Models\EquipmentAllocation;
use App\Models\MaterialReceipt;
use App\Models\MaterialReceiptItem;
use App\Models\Project;
use App\Models\PurchaseOrder;
use App\Models\SurveyPlan;
use App\Models\SurveyPlanMember;
use App\Models\SurveySubmission;
use App\Models\ExecutionTask;
use App\Models\ConstructionVehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;
use App\Models\Member;
use App\Models\SuperAdmin;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesConstructionActor;

    public function index(Request $request): Response
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $today = $now->copy()->toDateString();

        $actor = $this->constructionActor();
        $isProjectOwner = $this->isProjectOwner($actor);
        $ownedCompanyIds = $this->getOwnedCompanyIds($actor);
        $excludedCompanyIds = $actor instanceof SuperAdmin ? $this->getOtherSuperAdminCompanyIds($actor) : collect();
        $accessibleBaseIds = $this->getAccessibleProjectIds($actor);

        $requestedCompanyId = $request->query('company_id') ?: $request->query('id');
        $effectiveCompanyId = $isProjectOwner
            ? ($requestedCompanyId ? (int) $requestedCompanyId : null)
            : $this->getAccessibleCompanyId($actor);

        // 1. Projects scoping (every SuperAdmin including Project Owner stays scoped to their own accessible projects)
        $projectsQuery = Project::whereIn('id', $accessibleBaseIds);
        if ($effectiveCompanyId) {
            if ($excludedCompanyIds->contains($effectiveCompanyId)) {
                $projectsQuery->whereRaw('1 = 0');
            } else {
                $projectsQuery->where('company_id', $effectiveCompanyId);
            }
        }
        $accessibleProjectIds = $projectsQuery->pluck('id');

        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->whereIn('id', $accessibleProjectIds)
            ->latest()
            ->take(8)
            ->get();

        $allProjects = Project::whereIn('id', $accessibleProjectIds)->get(['id', 'status', 'current_stage']);

        $runningStages = [
            'budget_approved',
            'team_assigned',
            'survey_planned',
            'survey_in_progress',
            'drafting_in_progress',
            'drawing_approval_pending',
            'ready_for_construction',
            'planning',
            'survey',
            'finishing',
            'handover',
        ];
        $pendingStages = ['budget_pending', 'draft'];
        $completedStages = ['completed', 'closed'];

        $totalProjects = $allProjects->count();
        $runningProjects = $allProjects->whereIn('current_stage', $runningStages)->count();
        $completedProjects = $allProjects->whereIn('current_stage', $completedStages)->count();
        $pendingProjects = $allProjects->whereIn('current_stage', $pendingStages)->count();

        // 2. Employees scoping
        $membersQuery = Member::query();
        if ($effectiveCompanyId && !$excludedCompanyIds->contains($effectiveCompanyId)) {
            $company = Company::find($effectiveCompanyId);
            $companySuperAdminIds = SuperAdmin::where('company_id', $effectiveCompanyId)->pluck('id')->toArray();
            $teamMemberIds = \App\Models\ProjectTeamMember::whereIn('project_id', $accessibleProjectIds)->pluck('member_id')->toArray();

            $membersQuery->where(function ($q) use ($company, $companySuperAdminIds, $teamMemberIds, $actor) {
                if (!empty($companySuperAdminIds)) {
                    $q->whereIn('created_by', $companySuperAdminIds);
                }
                if ($company) {
                    $q->orWhere('company_name', $company->name);
                    if (!empty($company->legal_name)) {
                        $q->orWhere('company_name', $company->legal_name);
                    }
                }
                if (!empty($teamMemberIds)) {
                    $q->orWhereIn('id', $teamMemberIds);
                }
                if ($actor) {
                    $q->orWhere('created_by', $actor->getKey());
                }
            });
        } else {
            $ownedMemberIds = $this->getOwnedMemberIds($actor, $ownedCompanyIds);
            $teamMemberIds = \App\Models\ProjectTeamMember::whereIn('project_id', $accessibleProjectIds)->pluck('member_id');
            $scopedMemberIds = $ownedMemberIds->merge($teamMemberIds)->unique()->values();
            $membersQuery->whereIn('id', $scopedMemberIds);
        }
        $totalEmployees = (clone $membersQuery)->count();
        $activeEmployees = (clone $membersQuery)->where(fn($q) => $q->where('status', 1)->orWhere('status', 'active'))->count();

        // 3. Vehicles scoping
        $vehiclesQuery = ConstructionVehicle::query();
        if ($effectiveCompanyId && !$excludedCompanyIds->contains($effectiveCompanyId)) {
            $companySuperAdminIds = SuperAdmin::where('company_id', $effectiveCompanyId)->pluck('id')->toArray();
            $vehiclesQuery->where(function ($q) use ($effectiveCompanyId, $accessibleProjectIds, $companySuperAdminIds) {
                if ($accessibleProjectIds->isNotEmpty()) {
                    $q->whereIn('project_id', $accessibleProjectIds);
                } else {
                    $q->whereHas('project', fn($pq) => $pq->where('company_id', $effectiveCompanyId));
                }
                if (!empty($companySuperAdminIds)) {
                    $q->orWhere(function ($sq) use ($companySuperAdminIds) {
                        $sq->where('created_by_type', SuperAdmin::class)
                           ->whereIn('created_by_id', $companySuperAdminIds);
                    });
                }
            });
        } else {
            $vehiclesQuery->whereIn('project_id', $accessibleProjectIds);
        }
        $totalVehicles = (clone $vehiclesQuery)->count();
        $activeVehicles = (clone $vehiclesQuery)->whereIn('status', ['active', 'assigned', 'in_use'])->count();
        $vehicleIds = (clone $vehiclesQuery)->pluck('id');

        // 4. Equipment scoping
        $equipmentQuery = ConstructionEquipment::query();
        if ($effectiveCompanyId && !$excludedCompanyIds->contains($effectiveCompanyId)) {
            $companySuperAdminIds = SuperAdmin::where('company_id', $effectiveCompanyId)->pluck('id')->toArray();
            $equipmentQuery->where(function ($q) use ($effectiveCompanyId, $accessibleProjectIds, $companySuperAdminIds) {
                if ($accessibleProjectIds->isNotEmpty()) {
                    $q->whereIn('project_id', $accessibleProjectIds);
                } else {
                    $q->whereHas('project', fn($pq) => $pq->where('company_id', $effectiveCompanyId));
                }
                if (!empty($companySuperAdminIds)) {
                    $q->orWhere(function ($sq) use ($companySuperAdminIds) {
                        $sq->where('created_by_type', SuperAdmin::class)
                           ->whereIn('created_by_id', $companySuperAdminIds);
                    });
                }
            });
        } else {
            $equipmentQuery->whereIn('project_id', $accessibleProjectIds);
        }
        $totalEquipment = (clone $equipmentQuery)->count();
        $allocatedEquipment = EquipmentAllocation::whereIn('equipment_id', (clone $equipmentQuery)->pluck('id'))
            ->where('status', 'active')
            ->distinct()
            ->count('equipment_id');

        // 5. Clients scoping
        $accessibleClientIds = $this->getAccessibleClientIds($actor);
        $clientsQuery = Client::whereIn('id', $accessibleClientIds);
        if ($effectiveCompanyId) {
            $clientsQuery->where('company_id', $effectiveCompanyId);
        }
        $totalClients = (clone $clientsQuery)->count();
        $companyClients = (clone $clientsQuery)->where('client_type', 'company')->count();
        $govtClients = (clone $clientsQuery)->where('client_type', 'government')->count();

        // 6. Attendance scoping
        $attendanceQuery = AttendanceRecord::query();
        if ($accessibleProjectIds->isNotEmpty()) {
            $attendanceQuery->whereIn('project_id', $accessibleProjectIds);
        } elseif ($effectiveCompanyId && !$excludedCompanyIds->contains($effectiveCompanyId)) {
            $attendanceMemberIds = (clone $membersQuery)->pluck('id');
            if ($attendanceMemberIds->isNotEmpty()) {
                $attendanceQuery->whereIn('member_id', $attendanceMemberIds);
            } else {
                $attendanceQuery->whereRaw('1 = 0');
            }
        } else {
            $attendanceQuery->whereRaw('1 = 0');
        }
        $todayAttendance = (clone $attendanceQuery)->where('attendance_date', $today)->count();
        $presentToday = (clone $attendanceQuery)->where('attendance_date', $today)->where('status', 'approved')->count();
        $pendingAttendance = (clone $attendanceQuery)->where('status', 'pending')->count();
        $attendanceThisMonth = (clone $attendanceQuery)->whereBetween('attendance_date', [$monthStart->toDateString(), $today])->count();

        // 7. Survey scoping
        $surveyTeams = SurveyPlan::whereIn('project_id', $accessibleProjectIds)->distinct()->count('id');
        $surveyPlanIds = SurveyPlan::whereIn('project_id', $accessibleProjectIds)->pluck('id');
        $surveyTeamMembers = SurveyPlanMember::whereIn('survey_plan_id', $surveyPlanIds)->distinct()->count('member_id');

        // 8. GPS scoping
        $activeGPSVehicles = VehicleLocationPing::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('created_at', [$now->copy()->subHours(24), $now])
            ->distinct()
            ->count('vehicle_id');
        $totalGPSPingsToday = VehicleLocationPing::whereIn('vehicle_id', $vehicleIds)
            ->whereBetween('created_at', [$now->copy()->startOfDay(), $now])
            ->count();

        // 9. Stage distribution scoping
        $projectStageDistribution = Project::whereIn('id', $accessibleProjectIds)
            ->selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        // 10. Activity logs scoping
        $activityQuery = ConstructionActivityLog::with(['project'])->latest('created_at');
        if ($effectiveCompanyId && !$excludedCompanyIds->contains($effectiveCompanyId)) {
            $activityQuery->where(function ($q) use ($effectiveCompanyId, $accessibleProjectIds) {
                $q->where('company_id', $effectiveCompanyId);
                if ($accessibleProjectIds->isNotEmpty()) {
                    $q->orWhereIn('project_id', $accessibleProjectIds);
                }
            });
        } else {
            $activityQuery->where(function ($q) use ($accessibleProjectIds, $ownedCompanyIds) {
                $hasCondition = false;
                if ($accessibleProjectIds->isNotEmpty()) {
                    $q->orWhereIn('project_id', $accessibleProjectIds);
                    $hasCondition = true;
                }
                if ($ownedCompanyIds->isNotEmpty()) {
                    $q->orWhereIn('company_id', $ownedCompanyIds);
                    $hasCondition = true;
                }
                if (!$hasCondition) {
                    $q->whereRaw('1 = 0');
                }
            });
        }
        $recentActivity = $activityQuery->take(10)->get();

        return Inertia::render('SuperAdmin/Construction/Dashboard', [
            'stats' => [
                'projects' => [
                    'total' => $totalProjects,
                    'running' => $runningProjects,
                    'completed' => $completedProjects,
                    'pending' => $pendingProjects,
                ],
                'employees' => [
                    'total' => $totalEmployees,
                    'active' => $activeEmployees,
                ],
                'survey' => [
                    'teams' => $surveyTeams,
                    'members' => $surveyTeamMembers,
                ],
                'vehicles' => [
                    'total' => $totalVehicles,
                    'active' => $activeVehicles,
                ],
                'equipment' => [
                    'total' => $totalEquipment,
                    'allocated' => $allocatedEquipment,
                ],
                'clients' => [
                    'total' => $totalClients,
                    'company' => $companyClients,
                    'government' => $govtClients,
                ],
                'attendance' => [
                    'today' => $todayAttendance,
                    'presentToday' => $presentToday,
                    'pending' => $pendingAttendance,
                    'thisMonth' => $attendanceThisMonth,
                ],
                'gps' => [
                    'activeVehicles24h' => $activeGPSVehicles,
                    'pingsToday' => $totalGPSPingsToday,
                ],
                'stageDistribution' => $projectStageDistribution,

                'companies' => $isProjectOwner ? $ownedCompanyIds->count() : ($effectiveCompanyId ? 1 : 0),
                'clientsLegacy' => $totalClients,
                'projectsLegacy' => $totalProjects,
                'budgetPending' => Project::whereIn('id', $accessibleProjectIds)->where('current_stage', 'budget_pending')->count(),
                'teamAssigned' => Project::whereIn('id', $accessibleProjectIds)->where('current_stage', 'team_assigned')->count(),
                'surveyPlanned' => SurveyPlan::whereIn('project_id', $accessibleProjectIds)->whereIn('status', [SurveyPlan::STATUS_PLANNED, SurveyPlan::STATUS_IN_PROGRESS])->count(),
                'surveyApprovalsPending' => SurveySubmission::whereIn('project_id', $accessibleProjectIds)->where('status', SurveySubmission::STATUS_SUBMITTED)->count(),
                'draftingQueue' => DraftingJob::whereIn('project_id', $accessibleProjectIds)->whereIn('status', ['queued', 'in_progress'])->count(),
                'readyForConstruction' => Project::whereIn('id', $accessibleProjectIds)->where('current_stage', 'ready_for_construction')->count(),
                'executionTasks' => ExecutionTask::whereIn('project_id', $accessibleProjectIds)->count(),
                'dprPending' => DailyProgressReport::whereIn('project_id', $accessibleProjectIds)->where('status', 'submitted')->count(),
                'attendancePending' => (clone $attendanceQuery)->where('status', 'pending')->count(),
            ],
            'recentProjects' => $projects,
            'recentActivity' => $recentActivity,
            'companies' => $isProjectOwner ? Company::whereIn('id', $ownedCompanyIds)->orderBy('name')->get(['id', 'name']) : [],
            'selected_company_id' => $effectiveCompanyId,
            'is_project_owner' => $isProjectOwner,
            'projectStatusOptions' => [
                ['value' => 'planning', 'label' => 'Planning'],
                ['value' => 'survey', 'label' => 'Survey'],
                ['value' => 'finishing', 'label' => 'Finishing'],
                ['value' => 'handover', 'label' => 'Handover'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
        ]);
    }
}
