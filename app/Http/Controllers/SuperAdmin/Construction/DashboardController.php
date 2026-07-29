<?php

namespace App\Http\Controllers\SuperAdmin\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Construction\ActivityLog;
use App\Models\Construction\AttendanceRecord;
use App\Models\Construction\Client;
use App\Models\Construction\ClientInvoice;
use App\Models\Construction\ClientPayment;
use App\Models\Construction\Company;
use App\Models\Construction\DailyProgressReport;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\Equipment;
use App\Models\Construction\EquipmentAllocation;
use App\Models\Construction\MaterialReceipt;
use App\Models\Construction\MaterialReceiptItem;
use App\Models\Construction\Project;
use App\Models\Construction\PurchaseOrder;
use App\Models\Construction\SurveyPlan;
use App\Models\Construction\SurveyPlanMember;
use App\Models\Construction\SurveySubmission;
use App\Models\Construction\ExecutionTask;
use App\Models\Construction\Vehicle;
use App\Models\Construction\VehicleAssignment;
use App\Models\Construction\VehicleLocationPing;
use App\Models\Member;
use Carbon\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    use ResolvesConstructionActor;

    public function index(): Response
    {
        $now = Carbon::now();
        $monthStart = $now->copy()->startOfMonth();
        $today = $now->copy()->toDateString();

        $projects = Project::with(['company', 'client', 'latestBudget'])
            ->latest()
            ->take(8)
            ->get();

        $allProjects = Project::all(['id', 'status', 'current_stage']);

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
            'foundation',
            'structure',
            'finishing',
            'handover',
        ];
        $pendingStages = ['budget_pending', 'draft'];
        $completedStages = ['completed', 'closed'];

        $totalProjects = $allProjects->count();
        $runningProjects = $allProjects->whereIn('current_stage', $runningStages)->count();
        $completedProjects = $allProjects->whereIn('current_stage', $completedStages)->count();
        $pendingProjects = $allProjects->whereIn('current_stage', $pendingStages)->count();

        $totalEmployees = Member::count();
        $activeEmployees = Member::where('status', 'active')->count();

        $surveyTeams = SurveyPlan::distinct()->count('id');
        $surveyTeamMembers = SurveyPlanMember::distinct()->count('member_id');

        $totalVehicles = Vehicle::count();
        $activeVehicles = Vehicle::whereIn('status', ['active', 'assigned', 'in_use'])->count();

        $totalEquipment = Equipment::count();
        $allocatedEquipment = EquipmentAllocation::distinct()->count('equipment_id');

        $totalClients = Client::count();
        $companyClients = Client::where('client_type', 'company')->count();
        $govtClients = Client::where('client_type', 'government')->count();

        $revenue = ClientPayment::whereBetween('created_at', [$monthStart, $now])->sum('amount');
        $totalRevenue = ClientPayment::sum('amount');

        $invoiceAmountThisMonth = ClientInvoice::whereBetween('created_at', [$monthStart, $now])->sum('total_amount');

        $materialPurchaseExpense = PurchaseOrder::whereBetween('po_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'issued', 'partially_received', 'received', 'closed'])
            ->sum('total_amount');
        $materialReceiptExpense = MaterialReceiptItem::whereHas('receipt', function ($query) use ($monthStart, $today) {
            $query->whereBetween('created_at', [$monthStart, Carbon::parse($today)->endOfDay()]);
        })->sum('line_total');
        $labourDaysThisMonth = AttendanceRecord::whereBetween('attendance_date', [$monthStart->toDateString(), $today])
            ->whereIn('status', ['approved', 'present'])
            ->count();
        $standardDailyRate = 1200.00;
        $labourExpense = $labourDaysThisMonth * $standardDailyRate;

        $monthlyExpenses = (float) $materialPurchaseExpense
            + (float) $materialReceiptExpense
            + (float) $labourExpense;

        $todayAttendance = AttendanceRecord::where('attendance_date', $today)->count();
        $presentToday = AttendanceRecord::where('attendance_date', $today)->where('status', 'approved')->count();
        $pendingAttendance = AttendanceRecord::where('status', 'pending')->count();
        $attendanceThisMonth = AttendanceRecord::whereBetween('attendance_date', [$monthStart->toDateString(), $today])->count();

        $activeGPSVehicles = VehicleLocationPing::whereBetween('created_at', [$now->copy()->subHours(24), $now])
            ->distinct()
            ->count('vehicle_id');
        $totalGPSPingsToday = VehicleLocationPing::whereBetween('created_at', [$now->copy()->startOfDay(), $now])->count();

        $projectStageDistribution = Project::selectRaw('current_stage, COUNT(*) as count')
            ->groupBy('current_stage')
            ->pluck('count', 'current_stage')
            ->toArray();

        return Inertia::render('SuperAdmin/Construction/Dashboard', [
            'auth' => $this->constructionActor(),
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
                'finance' => [
                    'monthlyRevenue' => (float)$revenue,
                    'monthlyExpenses' => $monthlyExpenses,
                    'totalRevenue' => (float)$totalRevenue,
                    'monthlyInvoiced' => (float)$invoiceAmountThisMonth,
                    'breakdown' => [
                        'materialPurchase' => (float)$materialPurchaseExpense,
                        'materialReceipt' => (float)$materialReceiptExpense,
                        'labour' => (float)$labourExpense,
                        'labourDays' => $labourDaysThisMonth,
                        'standardDailyRate' => $standardDailyRate,
                    ],
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

                'companies' => Company::count(),
                'clientsLegacy' => Client::count(),
                'projectsLegacy' => Project::count(),
                'budgetPending' => Project::where('current_stage', 'budget_pending')->count(),
                'teamAssigned' => Project::where('current_stage', 'team_assigned')->count(),
                'surveyPlanned' => SurveyPlan::whereIn('status', ['planned', 'in_progress'])->count(),
                'surveyApprovalsPending' => SurveySubmission::where('status', 'submitted')->count(),
                'draftingQueue' => DraftingJob::whereIn('status', ['queued', 'in_progress'])->count(),
                'readyForConstruction' => Project::where('current_stage', 'ready_for_construction')->count(),
                'executionTasks' => ExecutionTask::count(),
                'dprPending' => DailyProgressReport::where('status', 'submitted')->count(),
                'attendancePending' => AttendanceRecord::where('status', 'pending')->count(),
            ],
            'recentProjects' => $projects,
            'recentActivity' => ActivityLog::with(['project'])
                ->latest('created_at')
                ->take(10)
                ->get(),
            'projectStatusOptions' => [
                ['value' => 'planning', 'label' => 'Planning'],
                ['value' => 'survey', 'label' => 'Survey'],
                ['value' => 'foundation', 'label' => 'Foundation'],
                ['value' => 'structure', 'label' => 'Structure'],
                ['value' => 'finishing', 'label' => 'Finishing'],
                ['value' => 'handover', 'label' => 'Handover'],
                ['value' => 'completed', 'label' => 'Completed'],
            ],
        ]);
    }
}
