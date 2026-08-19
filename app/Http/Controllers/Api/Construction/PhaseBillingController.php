<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use App\Models\Construction\ClientInvoice;
use App\Models\Construction\ClientInvoiceItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PhaseBillingController extends Controller
{
    public const STAGE_TO_PHASE_MAP = [
        'budget_pending'    => 'Phase 1 — Survey',
        'survey_planned'    => 'Phase 1 — Survey',
        'survey_in_progress'=> 'Phase 1 — Survey',
        'survey_completed'  => 'Phase 1 — Survey',
        'drawing_approval'  => 'Phase 2 — Drafting',
        'execution_planned' => 'Phase 3 — Execution',
        'execution_running' => 'Phase 3 — Execution',
        'execution_paused'  => 'Phase 3 — Execution',
        'execution_complete'=> 'Phase 3 — Execution',
        'material_purchase' => 'Phase 3 — Execution',
        'finishing'         => 'Phase 3 — Execution',
        'handover_pending'  => 'Phase 4 — Handover & Final Bill',
        'handover_done'     => 'Phase 4 — Handover & Final Bill',
        'completed'         => 'Phase 4 — Handover & Final Bill',
        'closed'            => 'Phase 4 — Handover & Final Bill',
    ];

    public const PHASE_PERCENT = [
        'Phase 1 — Survey'      => 30.0,
        'Phase 2 — Drafting'    => 25.0,
        'Phase 3 — Execution'   => 35.0,
        'Phase 4 — Handover & Final Bill' => 10.0,
    ];

    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function phaseBreakdown(Request $request, $projectId)
    {
        $project = Project::with(['latestBudget'])->find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        $totalBudget = (float) ($project->latestBudget?->approved_amount ?? $project->latestBudget?->estimated_amount ?? 0);

        $costByStage = DB::table('construction_material_issues AS mi')
            ->join('construction_material_issue_items AS mii', 'mii.material_issue_id', '=', 'mi.id')
            ->where('mi.project_id', $projectId)
            ->selectRaw('COALESCE(SUM(mii.quantity * mii.unit_price), 0) AS material_cost')
            ->first();
        $materialCost = (float) ($costByStage->material_cost ?? 0);

        $equipmentCost = (float) DB::table('construction_equipment_usage_logs')
            ->where('project_id', $projectId)
            ->whereNotNull('usage_cost')
            ->sum('usage_cost');

        $vehicleCost = (float) DB::table('construction_vehicle_location_pings')
            ->where('project_id', $projectId)
            ->count();

        $laborCost = 0;

        $totalUsed = $materialCost + $equipmentCost + $vehicleCost + $laborCost;

        $phases = [];
        $currentPhase = self::STAGE_TO_PHASE_MAP[$project->current_stage] ?? 'Phase 1 — Survey';

        foreach (self::PHASE_PERCENT as $phase => $pct) {
            $phaseBudget = $totalBudget * ($pct / 100);
            $isCurrent = $phase === $currentPhase;

            $stageInPhase = array_filter(
                self::STAGE_TO_PHASE_MAP,
                fn($p) => $p === $phase
            );
            $stageKeys = array_keys($stageInPhase);

            $taskCount = DB::table('construction_execution_tasks')
                ->where('project_id', $projectId)
                ->whereIn('lifecycle_stage', $stageKeys)
                ->count();
            $taskDone = DB::table('construction_execution_tasks')
                ->where('project_id', $projectId)
                ->whereIn('lifecycle_stage', $stageKeys)
                ->whereIn('status', ['completed', 'closed'])
                ->count();

            $phases[] = [
                'phase'               => $phase,
                'budget_share_pct'    => $pct,
                'phase_budget_amount' => round($phaseBudget, 2),
                'is_current'          => $isCurrent,
                'tasks_total'         => (int) $taskCount,
                'tasks_done'          => (int) $taskDone,
                'tasks_pct'           => $taskCount > 0 ? round(($taskDone / $taskCount) * 100, 1) : 0,
            ];
        }

        return response()->json([
            'success' => true,
            'project' => [
                'id'            => $project->id,
                'name'          => $project->name,
                'project_code'  => $project->project_code,
                'current_stage' => $project->current_stage,
                'current_phase' => $currentPhase,
            ],
            'total_budget'       => round($totalBudget, 2),
            'total_used_to_date' => round($totalUsed, 2),
            'breakdown_by_cost_head' => [
                'materials'   => round($materialCost, 2),
                'equipment'   => round($equipmentCost, 2),
                'vehicle'     => round($vehicleCost, 2),
                'labor'       => round($laborCost, 2),
            ],
            'phases' => $phases,
        ]);
    }

    public function generatePhaseInvoice(Request $request, $projectId)
    {
        $project = Project::with(['client:id,name,client_code,email,phone,billing_address', 'latestBudget'])->find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        $phase = self::STAGE_TO_PHASE_MAP[$project->current_stage] ?? 'Phase 1 — Survey';
        $totalBudget = (float) ($project->latestBudget?->approved_amount ?? $project->latestBudget?->estimated_amount ?? 0);

        if ($totalBudget <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'No approved budget found for this project. Approve budget before generating phase invoice.',
            ], 422);
        }

        $pct = self::PHASE_PERCENT[$phase] ?? 30.0;
        $phaseAmount = $totalBudget * ($pct / 100);
        $taxRate = (float) ($request->tax_rate_pct ?? 18.0);
        $taxableAmount = round($phaseAmount, 2);
        $taxAmount = round($taxableAmount * ($taxRate / 100), 2);
        $grandTotal = round($taxableAmount + $taxAmount, 2);

        $actor = $request->user();

        $stageLabels = array_keys(array_filter(self::STAGE_TO_PHASE_MAP, fn($p) => $p === $phase));
        $completedInPhase = DB::table('construction_execution_tasks')
            ->where('project_id', $projectId)
            ->whereIn('lifecycle_stage', $stageLabels)
            ->whereIn('status', ['completed', 'closed'])
            ->count();
        $totalInPhase = DB::table('construction_execution_tasks')
            ->where('project_id', $projectId)
            ->whereIn('lifecycle_stage', $stageLabels)
            ->count();
        $progressPct = $totalInPhase > 0 ? round(($completedInPhase / $totalInPhase) * 100, 1) : 100;

        $existing = ClientInvoice::where('project_id', $projectId)
            ->where('phase_label', $phase)
            ->whereIn('status', ['draft', 'sent', 'paid'])
            ->first();
        if ($existing && ! $request->boolean('force_new', false)) {
            return response()->json([
                'success'           => false,
                'message'           => 'An invoice for this phase already exists. Pass force_new=true to override.',
                'existing_invoice_id' => $existing->id,
                'existing_invoice_no' => $existing->invoice_number,
            ], 409);
        }

        $invoiceNumber = strtoupper('INV-' . $project->project_code . '-' . str_replace([' ', '—', '&'], '', $phase) . '-' . date('my'));

        $invoice = ClientInvoice::create([
            'company_id'        => $project->company_id,
            'project_id'        => $projectId,
            'client_id'         => $project->client_id,
            'invoice_number'    => $invoiceNumber,
            'invoice_date'      => now()->toDateString(),
            'due_date'          => now()->addDays(15)->toDateString(),
            'phase_label'       => $phase,
            'current_stage'     => $project->current_stage,
            'stage_progress_pct'=> $progressPct,
            'tax_rate_pct'      => $taxRate,
            'taxable_amount'    => $taxableAmount,
            'tax_amount'        => $taxAmount,
            'grand_total'       => $grandTotal,
            'amount_paid'       => 0,
            'balance_due'       => $grandTotal,
            'status'            => 'draft',
            'notes'             => $request->notes ?? ('Phase-wise bill for ' . $phase . ' @ ' . $pct . '% of total project value.'),
            'raised_by_type'    => $actor ? get_class($actor) : null,
            'raised_by_id'      => $actor?->id,
            'currency'          => 'INR',
        ]);

        ClientInvoiceItem::create([
            'client_invoice_id' => $invoice->id,
            'line_no'           => 1,
            'item_description'  => $phase . ' — ' . $progressPct . '% work completed as per lifecycle stage ' . $project->current_stage,
            'quantity'          => 1,
            'unit_price'        => $taxableAmount,
            'line_total'        => $taxableAmount,
            'phase_label'       => $phase,
        ]);

        return response()->json([
            'success'       => true,
            'message'       => 'Phase invoice generated — ' . $phase . '.',
            'invoice'       => $invoice->fresh(),
            'breakdown'     => [
                'phase'              => $phase,
                'budget_pct_applied' => $pct,
                'taxable_amount'     => $taxableAmount,
                'gst_pct'            => $taxRate,
                'gst_amount'         => $taxAmount,
                'grand_total_inr'    => $grandTotal,
            ],
            'project_stage_progress' => $progressPct . '%',
        ], 201);
    }
}
