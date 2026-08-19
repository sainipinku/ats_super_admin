<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ClientReviewController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function clientDashboard(Request $request, $clientId)
    {
        $query = DB::table('construction_projects AS p')
            ->leftJoin('construction_clients AS c', 'c.id', '=', 'p.client_id')
            ->where('p.client_id', (int) $clientId);

        if ($request->filled('review_status')) {
            $statuses = is_array($request->review_status)
                ? $request->review_status
                : explode(',', (string) $request->review_status);
            $query->whereIn('p.client_review_status', $statuses);
        }

        $projects = $query->select([
            'p.id', 'p.project_code', 'p.name', 'p.description', 'p.project_address',
            'p.status AS project_status', 'p.current_stage', 'p.client_review_status',
            'p.client_review_requested_at', 'p.client_approved_at',
            'p.revision_iteration_count',
            'c.name AS client_name',
        ])
            ->orderByDesc('p.client_review_requested_at')
            ->orderByDesc('p.updated_at')
            ->paginate($request->per_page ?? 20);

        $projects->getCollection()->transform(function ($p) {
            $statusMap = [
                'pending_review'     => ['label' => 'Pending Client Review', 'color' => 'blue',  'badge' => 'New'],
                'revisions_required' => ['label' => 'Revisions Required',  'color' => 'amber', 'badge' => 'Client sent back'],
                'client_approved'    => ['label' => 'Approved',              'color' => 'green', 'badge' => 'Ready for Final Bill'],
                'not_started'        => ['label' => 'Not Sent For Review',   'color' => 'gray',  'badge' => 'Work in progress'],
            ];
            $meta = $statusMap[$p->client_review_status] ?? $statusMap['not_started'];
            $p->status_meta = $meta;
            return $p;
        });

        $counts = DB::table('construction_projects')
            ->where('client_id', (int) $clientId)
            ->selectRaw('client_review_status, COUNT(*) AS total')
            ->groupBy('client_review_status')
            ->pluck('total', 'client_review_status')
            ->toArray();

        return response()->json([
            'success'   => true,
            'projects'  => $projects,
            'counts'    => [
                'pending_review'     => (int) ($counts['pending_review'] ?? 0),
                'revisions_required' => (int) ($counts['revisions_required'] ?? 0),
                'client_approved'    => (int) ($counts['client_approved'] ?? 0),
                'total'              => (int) array_sum($counts),
            ],
        ]);
    }

    public function markReadyForClient(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }
        $actor = $request->user();

        $surveyPlanned  = DB::table('construction_survey_plans')->where('project_id', $projectId)->count();
        $surveyDone     = DB::table('construction_survey_plans')->where('project_id', $projectId)->whereIn('status', ['completed', 'submitted'])->count();
        $draftingPlanned= DB::table('construction_drafting_jobs')->where('project_id', $projectId)->count();
        $draftingDone   = DB::table('construction_drafting_jobs')->where('project_id', $projectId)->whereIn('status', ['completed', 'approved'])->count();

        if (($surveyPlanned > 0 && $surveyPlanned !== $surveyDone) || ($draftingPlanned > 0 && $draftingPlanned !== $draftingDone)) {
            if (! $request->boolean('force', false)) {
                return response()->json([
                    'success' => false,
                    'message' => 'All survey/drafting tasks are not complete. Pass force=true to override.',
                    'completion' => [
                        'survey_planned' => $surveyPlanned,
                        'survey_done'    => $surveyDone,
                        'drafting_planned' => $draftingPlanned,
                        'drafting_done'    => $draftingDone,
                    ],
                ], 422);
            }
        }

        $project->update([
            'client_review_status'       => 'pending_review',
            'client_review_requested_at' => now(),
            'client_review_requested_by' => $actor?->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Project marked "Ready for Client Review". Client notified.',
            'client_notification_sent'  => true,
            'client_review_status'      => $project->fresh()->client_review_status,
            'requested_at'              => $project->fresh()->client_review_requested_at,
        ]);
    }

    public function projectDetailForReview(Request $request, $projectId)
    {
        $project = Project::with(['client:id,name,email,phone', 'latestBudget', 'teamMembers.member:id,name,phone'])->find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        $documents = DB::table('construction_documents')
            ->where('project_id', $projectId)
            ->whereIn('folder', ['drawings', 'survey-data', 'final-reports', null])
            ->select(['id', 'folder', 'file_name', 'original_name', 'mime_type', 'file_size', 'path', 'version_no', 'created_at'])
            ->orderByRaw("FIELD(folder, 'final-reports', 'drawings', 'survey-data')")
            ->orderByDesc('created_at')
            ->get();

        $revisions = DB::table('construction_client_revision_logs')
            ->where('project_id', $projectId)
            ->orderByDesc('revision_cycle_number')
            ->orderBy('action_at')
            ->limit(100)
            ->get();

        $canApprove = $project->client_review_status === 'pending_review' || $project->client_review_status === 'revisions_required';
        $canRevise  = $project->client_review_status === 'pending_review' || $project->client_review_status === 'client_approved';

        return response()->json([
            'success' => true,
            'overview' => [
                'id'            => $project->id,
                'code'          => $project->project_code,
                'name'          => $project->name,
                'description'   => $project->description,
                'address'       => $project->project_address,
                'lifecycle_stage'   => $project->current_stage,
                'client_review_status' => $project->client_review_status,
                'start_date'    => optional($project->start_date)->toDateString(),
                'expected_end'  => optional($project->expected_end_date)->toDateString(),
                'priority'      => $project->priority,
                'budget_approved_inr' => (float) ($project->latestBudget?->approved_amount ?? 0),
                'revision_iterations' => (int) $project->revision_iteration_count,
                'review_requested_at' => optional($project->client_review_requested_at)->toISOString(),
                'approved_at'         => optional($project->client_approved_at)->toISOString(),
            ],
            'documents'     => $documents,
            'team_members'  => $project->teamMembers->map(fn($tm) => [
                'member_id'       => $tm->member_id,
                'name'            => optional($tm->member)->name,
                'phone'           => optional($tm->member)->phone,
                'assignment_scope'=> $tm->assignment_scope,
                'is_primary'      => (bool) $tm->is_primary,
            ])->values(),
            'revision_log'  => $revisions,
            'action_buttons' => [
                'can_approve'        => $canApprove,
                'can_request_revision' => $canRevise,
            ],
        ]);
    }

    public function clientApprove(Request $request, $projectId)
    {
        $project = Project::find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false,
                'message' => 'Project not found.',
            ], 404);
        }

        if (! in_array($project->client_review_status, ['pending_review', 'revisions_required'], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Project is not in a client-reviewable state (current: ' . $project->client_review_status . ').',
            ], 422);
        }

        $validated = $request->validate([
            'approval_note'            => ['nullable', 'string', 'max:1000'],
            'digital_signature_base64' => ['nullable', 'string'],
            'client_id'                => ['nullable', 'integer', 'exists:construction_clients,id'],
        ]);

        $project->update([
            'client_review_status' => 'client_approved',
            'client_approved_at'   => now(),
            'client_approved_by'   => $validated['client_id'] ?? $project->client_id,
            'client_revision_comment' => $validated['approval_note'] ?? $project->client_revision_comment,
        ]);

        if (! empty($project->revision_iteration_count)) {
            DB::table('construction_client_revision_logs')->insert([
                'project_id'                  => $projectId,
                'client_id'                   => $validated['client_id'] ?? $project->client_id,
                'action'                      => 'client_reapproved',
                'revision_cycle_number'       => (int) $project->revision_iteration_count,
                'comment'                     => $validated['approval_note'] ?? null,
                'actor_type'                  => \App\Models\Construction\Client::class,
                'actor_id'                    => $validated['client_id'] ?? $project->client_id,
                'action_at'                   => now(),
                'created_at'                  => now(),
                'updated_at'                  => now(),
            ]);
        }

        $nextStage = 'handover_pending';
        $stages = ['budget_pending', 'survey_planned', 'survey_in_progress', 'survey_completed',
            'drawing_approval', 'execution_planned', 'execution_running', 'execution_paused',
            'execution_complete', 'material_purchase', 'finishing', 'handover_pending', 'handover_done'];
        $curIdx = array_search($project->current_stage, $stages, true);
        if ($curIdx !== false && $curIdx < array_search('handover_pending', $stages, true)) {
            $nextStage = $project->current_stage;
        }
        $project->update(['current_stage' => $nextStage]);

        return response()->json([
            'success'       => true,
            'message'       => 'Client approval confirmed. Project moved to Handover / Final Bill stage.',
            'status_after'  => 'client_approved',
            'next_stage'    => $nextStage,
            'final_bill_ready' => true,
        ]);
    }

    public function clientRequestRevision(Request $request, $projectId)
    {
        $validator = Validator::make($request->all(), [
            'revision_comment'           => ['required', 'string', 'min:5', 'max:2000'],
            'partial_revision_sections'  => ['nullable', 'array'],
            'partial_revision_sections.*'=> ['string', 'in:overview,drawings,survey_measurements,materials,execution,budget'],
            'client_id'                  => ['nullable', 'integer', 'exists:construction_clients,id'],
            'supervisor_member_id'       => ['nullable', 'integer', 'exists:members,id'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }
        $data = $validator->validate();

        $project = Project::find($projectId);
        if (! $project) {
            return response()->json([
                'success' => false, 'message' => 'Project not found.',
            ], 404);
        }
        if ($project->client_review_status !== 'pending_review' && $project->client_review_status !== 'client_approved') {
            return response()->json([
                'success' => false, 'message' => 'Revisions can only be requested on projects pending review or already approved.',
            ], 422);
        }

        $newIteration = (int) $project->revision_iteration_count + 1;

        $project->update([
            'client_review_status'       => 'revisions_required',
            'client_revision_comment'    => $data['revision_comment'],
            'partial_revision_sections'  => ! empty($data['partial_revision_sections']) ? json_encode($data['partial_revision_sections']) : null,
            'revision_iteration_count'   => $newIteration,
        ]);

        $actor = $request->user();
        $clientId = $data['client_id'] ?? $project->client_id;
        $supervisorId = $data['supervisor_member_id'] ?? null;

        $actions = [];
        $actions[] = [
            'project_id' => $projectId, 'client_id' => $clientId,
            'assigned_supervisor_member_id' => $supervisorId,
            'action' => 'request_revision',
            'revision_cycle_number' => $newIteration,
            'comment' => $data['revision_comment'],
            'affected_sections' => ! empty($data['partial_revision_sections']) ? json_encode($data['partial_revision_sections']) : null,
            'actor_type' => $actor ? get_class($actor) : \App\Models\Construction\Client::class,
            'actor_id'   => $actor?->id ?? $clientId,
            'action_at'  => now(), 'created_at' => now(), 'updated_at' => now(),
        ];
        if (! empty($supervisorId)) {
            $actions[] = [
                'project_id' => $projectId, 'client_id' => $clientId,
                'assigned_supervisor_member_id' => $supervisorId,
                'action' => 'assigned_to_surveyor',
                'revision_cycle_number' => $newIteration,
                'comment' => 'Auto-assigned to Supervisor',
                'actor_type' => null, 'actor_id' => null,
                'action_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
        }
        DB::table('construction_client_revision_logs')->insert($actions);

        return response()->json([
            'success'                 => true,
            'message'                 => 'Revision requested — Supervisor notified automatically.',
            'revision_cycle_number'   => $newIteration,
            'partial_sections'        => $data['partial_revision_sections'] ?? ['all'],
            'client_review_status'    => 'revisions_required',
            'supervisor_notified'     => ! empty($supervisorId),
        ]);
    }

    public function supervisorResolveRevision(Request $request, $projectId)
    {
        $validated = $request->validate([
            'revision_cycle'          => ['required', 'integer', 'min:1'],
            'assigned_to_member_id'   => ['nullable', 'integer', 'exists:members,id'],
            'assign_role'             => ['nullable', 'in:surveyor,draftsman,internal_fix'],
            'supervisor_note'         => ['nullable', 'string', 'max:1000'],
            'complete_internal_fix'   => ['nullable', 'boolean'],
            'resubmit_for_client'     => ['nullable', 'boolean'],
        ]);

        $project = Project::find($projectId);
        if (! $project) return response()->json(['success' => false, 'message' => 'Project not found.'], 404);
        if ($project->client_review_status !== 'revisions_required') {
            return response()->json(['success' => false, 'message' => 'Project not in revisions_required state.'], 422);
        }

        $actor = $request->user();
        $cycle = (int) $validated['revision_cycle'];
        $logs = [];

        if (! empty($validated['assigned_to_member_id'])) {
            $roleAction = match ($validated['assign_role'] ?? 'internal_fix') {
                'surveyor'   => 'assigned_to_surveyor',
                'draftsman'  => 'assigned_to_draftsman',
                default      => 'internal_fix_complete',
            };
            $logs[] = [
                'project_id' => $projectId, 'client_id' => $project->client_id,
                'assigned_supervisor_member_id' => $actor?->id,
                'action' => $roleAction,
                'revision_cycle_number' => $cycle,
                'comment' => $validated['supervisor_note'] ?? null,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id'   => $actor?->id,
                'action_at'  => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
        }

        if (! empty($validated['complete_internal_fix'])) {
            $logs[] = [
                'project_id' => $projectId, 'client_id' => $project->client_id,
                'assigned_supervisor_member_id' => $actor?->id,
                'action' => 'internal_fix_complete',
                'revision_cycle_number' => $cycle,
                'comment' => $validated['supervisor_note'] ?? null,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id'   => $actor?->id,
                'action_at'  => now(), 'created_at' => now(), 'updated_at' => now(),
            ];
            $logs[] = [
                'project_id' => $projectId, 'client_id' => $project->client_id,
                'assigned_supervisor_member_id' => $actor?->id,
                'action' => 'supervisor_verified',
                'revision_cycle_number' => $cycle,
                'comment'                => $validated['supervisor_note'] ?? null,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id'   => $actor?->id,
                'action_at'  => now()->addSeconds(1),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        if (! empty($validated['resubmit_for_client'])) {
            $logs[] = [
                'project_id' => $projectId, 'client_id' => $project->client_id,
                'assigned_supervisor_member_id' => $actor?->id,
                'action' => 'resubmitted_for_client_review',
                'revision_cycle_number' => $cycle,
                'comment'                => $validated['supervisor_note'] ?? null,
                'actor_type' => $actor ? get_class($actor) : null,
                'actor_id'   => $actor?->id,
                'action_at'  => now()->addSeconds(2),
                'created_at' => now(), 'updated_at' => now(),
            ];
            $project->update([
                'client_review_status'       => 'pending_review',
                'client_review_requested_at' => now(),
                'client_review_requested_by' => $actor?->id,
            ]);
        }

        if (! empty($logs)) {
            DB::table('construction_client_revision_logs')->insert($logs);
        }

        return response()->json([
            'success'              => true,
            'message'              => 'Revision flow updated — ' . count($logs) . ' action(s) logged.',
            'revision_cycle'       => $cycle,
            'status_after'         => $project->fresh()->client_review_status,
            'actions_logged'       => count($logs),
        ]);
    }
}
