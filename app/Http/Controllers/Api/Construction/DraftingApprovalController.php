<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Construction\DraftingJob;
use App\Models\Construction\DrawingApproval;
use App\Models\Construction\MemberRoleAssignment;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class DraftingApprovalController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    private function isSeniorDraftsman($memberId, $projectId = null): bool
    {
        $q = MemberRoleAssignment::where('member_id', (int) $memberId)
            ->where('is_senior', true);
        if (! empty($projectId)) {
            $q->where(function ($qq) use ($projectId) {
                $qq->whereNull('project_id')->orWhere('project_id', (int) $projectId);
            });
        }
        return (bool) $q->exists();
    }

    private function canSelfDraft($memberId, $projectId = null): bool
    {
        $q = MemberRoleAssignment::where('member_id', (int) $memberId)
            ->where('can_self_draft', true);
        if (! empty($projectId)) {
            $q->where(function ($qq) use ($projectId) {
                $qq->whereNull('project_id')->orWhere('project_id', (int) $projectId);
            });
        }
        return (bool) $q->exists();
    }

    public function draftingCapabilities(Request $request, $memberId)
    {
        $projectId = $request->project_id;
        $member = Member::find($memberId);
        if (! $member) {
            return response()->json([
                'success' => false,
                'message' => 'Member not found.',
            ], 404);
        }
        return response()->json([
            'success'         => true,
            'member'          => ['id' => $member->id, 'name' => $member->name],
            'is_senior'       => $this->isSeniorDraftsman($memberId, $projectId),
            'can_self_draft'  => $this->canSelfDraft($memberId, $projectId),
        ]);
    }

    public function listDrawingApprovals(Request $request, $projectId)
    {
        $rows = DrawingApproval::query()
            ->with([
                'drawingRevision:id,drafting_job_id,revision_no,status',
                'drawingRevision.draftingJob:id,project_id,status',
            ])
            ->whereHas('drawingRevision.draftingJob', function ($q) use ($projectId) {
                $q->where('project_id', (int) $projectId);
            })
            ->when($request->decision, fn($q) => $q->where('decision', $request->decision))
            ->when($request->review_level, fn($q) => $q->where('review_level', $request->review_level))
            ->latest('id')
            ->paginate($request->per_page ?? 20);

        return response()->json([
            'success'   => true,
            'approvals' => $rows,
        ]);
    }

    public function requestApproval(Request $request, $revisionId)
    {
        $revision = DB::table('construction_drawing_revisions')->where('id', (int) $revisionId)->first();
        if (! $revision) {
            return response()->json([
                'success' => false,
                'message' => 'Drawing revision not found.',
            ], 404);
        }

        $job = DraftingJob::find($revision->drafting_job_id);
        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Drafting job not found.',
            ], 404);
        }

        $actor = $request->user();
        $draftsmanId = $job->assigned_to_member_id;
        $senior = $draftsmanId ? $this->isSeniorDraftsman($draftsmanId, $job->project_id) : false;
        $self   = $draftsmanId ? $this->canSelfDraft($draftsmanId, $job->project_id) : false;

        $skipJunior = $senior || $request->boolean('force_skip_junior', false);

        $approval = DrawingApproval::create([
            'project_id'           => $job->project_id,
            'drawing_revision_id'  => (int) $revisionId,
            'requested_by_type'    => $actor ? get_class($actor) : null,
            'requested_by_id'      => $actor?->id,
            'requested_at'         => now(),
            'decision'             => 'pending',
            'remarks'              => $request->remarks ?? null,
            'skip_junior_review'   => $skipJunior,
            'review_level'         => $skipJunior ? 'senior' : 'junior',
        ]);

        DB::table('construction_drawing_revisions')
            ->where('id', (int) $revisionId)
            ->update(['status' => 'reviewing', 'updated_at' => now()]);

        return response()->json([
            'success'                 => true,
            'message'                 => $skipJunior
                ? 'Approval requested — Senior Draftsman detected; skipping junior review.'
                : 'Approval requested — Junior review queued.',
            'approval_id'             => $approval->id,
            'skip_junior_review'      => $skipJunior,
            'current_review_level'    => $approval->review_level,
            'draftsman_is_senior'     => $senior,
            'surveyor_can_self_draft' => $self,
        ]);
    }

    public function approve(Request $request, $approvalId)
    {
        $validator = Validator::make($request->all(), [
            'decision'       => ['required', 'in:approved,rejected'],
            'review_level'   => ['required', 'in:junior,senior,final'],
            'remarks'        => ['nullable', 'string', 'max:1000'],
            'skip_to_final'  => ['nullable', 'boolean'],
        ]);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors'  => $validator->errors(),
            ], 422);
        }
        $data = $validator->validate();

        $approval = DrawingApproval::with('drawingRevision')->find($approvalId);
        if (! $approval) {
            return response()->json([
                'success' => false,
                'message' => 'Approval record not found.',
            ], 404);
        }

        $actor = $request->user();
        $approval->update([
            'decision'         => $data['decision'],
            'review_level'     => $data['skip_to_final'] ? 'final' : $data['review_level'],
            'remarks'          => $data['remarks'] ?? $approval->remarks,
            'approved_by_type' => $actor ? get_class($actor) : null,
            'approved_by_id'   => $actor?->id,
            'approved_at'      => $data['decision'] === 'approved' ? now() : null,
        ]);

        if ($data['decision'] === 'approved') {
            if ($approval->review_level === 'final') {
                DB::table('construction_drawing_revisions')
                    ->where('id', $approval->drawing_revision_id)
                    ->update(['status' => 'approved', 'updated_at' => now()]);
            } elseif ($approval->review_level === 'junior' && ! $approval->skip_junior_review) {
                $approval->update([
                    'decision'     => 'pending',
                    'review_level' => 'senior',
                    'approved_at'  => null,
                ]);
                $msg = 'Junior review passed — queued for Senior review.';
            } else {
                $msg = ucfirst($approval->review_level) . ' review approved.';
            }
        } else {
            DB::table('construction_drawing_revisions')
                ->where('id', $approval->drawing_revision_id)
                ->update(['status' => 'changes_requested', 'updated_at' => now()]);
        }

        return response()->json([
            'success'  => true,
            'message'  => $msg ?? 'Review decision recorded.',
            'approval' => $approval->fresh(),
        ]);
    }

    public function rejectDraft(Request $request, $draftingJobId)
    {
        $validated = $request->validate([
            'rejection_reason'      => ['required', 'string', 'min:3', 'max:1500'],
            'reassign_to_member_id' => ['nullable', 'integer', 'exists:members,id'],
        ]);

        $job = DraftingJob::find($draftingJobId);
        if (! $job) {
            return response()->json([
                'success' => false,
                'message' => 'Drafting job not found.',
            ], 404);
        }

        $actor = $request->user();
        $job->update([
            'status'               => 'rejected',
            'rejection_reason'     => $validated['rejection_reason'],
            'rejected_by_member_id' => $actor?->id,
            'rejected_at'          => now(),
            'rejection_count'      => DB::raw('rejection_count + 1'),
        ]);

        if (! empty($validated['reassign_to_member_id'])) {
            $job->update(['assigned_to_member_id' => (int) $validated['reassign_to_member_id']]);
        }

        return response()->json([
            'success'         => true,
            'message'         => 'Draft rejected and sent back to Draftsman for correction.',
            'rejection_count' => $job->fresh()->rejection_count,
            'assigned_to'     => $job->assigned_to_member_id,
        ]);
    }
}
