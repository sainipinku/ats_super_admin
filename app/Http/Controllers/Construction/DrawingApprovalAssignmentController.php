<?php

namespace App\Http\Controllers\Construction;

use App\Http\Controllers\Concerns\ResolvesConstructionActor;
use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Services\Construction\CadApprovalAssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DrawingApprovalAssignmentController extends Controller
{
    use ResolvesConstructionActor;

    public function __construct(
        private readonly CadApprovalAssignmentService $assignmentService
    ) {
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        $actor = $this->constructionActor($request);

        abort_unless(
            $actor && $this->assignmentService->canManageApprovalAssignment($actor, $project),
            403,
            'Only company Admin or Super Admin with approval assignment permission can assign CAD approvals.'
        );

        $this->ensureProjectAccess($project, $actor);

        $validated = $request->validate([
            'assignee_key' => ['required', 'string', 'max:64'],
            'drafting_job_id' => ['nullable', 'integer', 'min:1'],
            'drawing_revision_id' => ['nullable', 'integer', 'min:1'],
            'drawing_approval_id' => ['nullable', 'integer', 'min:1'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $approval = $this->assignmentService->assign(
            project: $project,
            actor: $actor,
            assigneeKey: $validated['assignee_key'],
            draftingJobId: isset($validated['drafting_job_id']) ? (int) $validated['drafting_job_id'] : null,
            drawingRevisionId: isset($validated['drawing_revision_id']) ? (int) $validated['drawing_revision_id'] : null,
            drawingApprovalId: isset($validated['drawing_approval_id']) ? (int) $validated['drawing_approval_id'] : null,
            remarks: $validated['remarks'] ?? null,
        );

        $assigneeName = $approval->assignedTo?->name ?? 'selected admin';

        return back()->with('success', "CAD approval assigned to {$assigneeName}.");
    }
}
