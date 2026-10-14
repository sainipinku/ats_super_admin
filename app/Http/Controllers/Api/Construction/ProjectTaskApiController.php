<?php

namespace App\Http\Controllers\Api\Construction;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Task;
use App\Rules\ValidMediaUpload;
use App\Services\Construction\TaskManagementService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * @tags Project Tasks
 */
class ProjectTaskApiController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:sanctum'),
            new Middleware('ability:task.manage,construction.view'),
        ];
    }

    public function __construct(
        public readonly TaskManagementService $tasks,
    ) {
    }

    /**
     * List Project Tasks
     *
     * Retrieves all tasks under a specific construction project with filtering by status, priority, assignee, and search.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function index(Request $request, Project $project): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'array'],
            'status.*' => ['string', Rule::in(['planned','pending','in_progress','review','completed','blocked','cancelled'])],
            'priority' => ['nullable', 'array'],
            'priority.*' => ['string', Rule::in(['low','medium','high','critical'])],
            'assignee_id' => ['nullable', 'array'],
            'assignee_id.*' => ['integer', 'exists:members,id'],
            'task_source' => ['nullable', 'array'],
            'task_source.*' => ['string', Rule::in(['admin_created','member_manual','execution_plan_seed','survey_checklist_seed'])],
            'execution_plan_id' => ['nullable', 'integer'],
            'survey_plan_id' => ['nullable', 'integer'],
            'parent_task_id' => ['nullable', 'integer'],
            'only_roots' => ['nullable', 'boolean'],
            'search' => ['nullable', 'string', 'max:200'],
            'sort_by' => ['nullable', 'string'],
            'sort_dir' => ['nullable', 'string', Rule::in(['asc','desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
            'include' => ['nullable', 'string'],
        ]);

        $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : null;
        $result = $this->tasks->index($project, $filters, $perPage);
        $result = $this->appendSignedUrlsToDocuments($result);

        return response()->json([
            'success' => true,
            'data' => $result,
            'project_id' => $project->id,
        ]);
    }

    /**
     * Create Project Task
     *
     * Creates a new execution task for a construction project with assignee, dates, deliverables, and attachments.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function store(Request $request, Project $project): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return response()->json([
                'success' => false,
                'error_code' => 'AUTH_REQUIRED',
                'message' => 'Authentication required.',
            ], 401);
        }

        try {
            $payload = $this->tasks->validateCreatePayload($project, $request->all());
            $task = $this->tasks->create($project, $payload, $actor, $request);
            $task = $this->appendSignedUrlsToDocuments($task);

            return response()->json([
                'success' => true,
                'message' => 'Task created successfully.',
                'data' => $task,
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'message' => 'Please correct the submitted data.',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to create task.',
                'trace' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    /**
     * Get Task Details
     *
     * Retrieves full details, checklist items, comments, and status history for a specific task.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function show(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);

        $include = array_filter(explode(',', (string) $request->input('include', '')));
        $with = array_values(array_intersect($include, [
            'comments.commenter','statusTransitions.commenter','statusTransitions.documents',
            'checklistItems.completedBy','documents','dprs','children','parent',
        ]));

        $loaded = $task->loadMissing(array_merge($this->tasks->defaultIncludes(), $with));
        $loaded = $this->appendSignedUrlsToDocuments($loaded);

        return response()->json([
            'success' => true,
            'data' => $loaded,
        ]);
    }

    /**
     * Update Project Task
     *
     * Modifies title, timeline, priority, description, or quantities of an existing task.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function update(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }

        try {
            $task = $this->tasks->update($task, $request->all(), $actor, $request);
            $task = $this->appendSignedUrlsToDocuments($task);
            return response()->json([
                'success' => true,
                'message' => 'Task updated.',
                'data' => $task,
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to update task.',
            ], 500);
        }
    }

    /**
     * Delete Project Task
     *
     * Deletes a task from the project.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function destroy(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }
        $this->tasks->deleteTask($task, $actor, $request);
        return response()->json(null, 204);
    }

    /**
     * Assign Members to Task
     *
     * Updates or sets member assignees for a construction task.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function assignments(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }

        $rows = $request->validate([
            'assignments' => ['required', 'array'],
            'assignments.*.member_id' => ['required', 'integer', 'exists:members,id'],
            'assignments.*.assignment_role' => ['nullable', Rule::in(['owner','executor','reviewer','checker','verifier'])],
            'assignments.*.is_primary' => ['nullable', 'boolean'],
            'assignments.*.assigned_from' => ['nullable', 'date'],
            'assignments.*.assigned_until' => ['nullable', 'date', 'after_or_equal:assignments.*.assigned_from'],
        ])['assignments'] ?? [];

        try {
            $result = $this->tasks->replaceAssignments($task, $rows, $actor);
            return response()->json([
                'success' => true,
                'message' => 'Assignments updated.',
                'data' => $result,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Failed to update assignments.',
            ], 500);
        }
    }

    /**
     * List Task Checklists
     *
     * Retrieves all ordered checklist items belonging to a project task.
     *
     * @tags Project Tasks & Execution
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function checklistIndex(Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        return response()->json([
            'success' => true,
            'data' => $task->checklistItems()->orderBy('sort_order')->orderBy('id')->get(),
        ]);
    }

    /**
     * Batch Add Task Checklists
     *
     * Replaces or bulk inserts interactive checklist items for a task.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function checklistStoreBatch(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }
        $items = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['nullable', 'integer', 'exists:task_checklist_items,id'],
            'items.*.item_title' => ['required', 'string', 'max:500'],
            'items.*.day_number' => ['nullable', 'integer', 'min:0', 'max:255'],
            'items.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'items.*.is_completed' => ['nullable', 'boolean'],
            'items.*.client_reference' => ['nullable', 'uuid'],
        ])['items'] ?? [];
        try {
            $list = $this->tasks->replaceChecklistItems($task, $items, $actor);
            return response()->json([
                'success' => true,
                'message' => 'Checklist saved.',
                'data' => $list,
            ]);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Checklist save failed.',
            ], 500);
        }
    }

    /**
     * Update Task Checklist Item
     *
     * Updates title, day number, sort order, or completion status of an individual checklist item.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param int $checklistId
     * @return JsonResponse
     */
    public function checklistUpdate(Request $request, Project $project, int $checklistId): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }
        $payload = $request->validate([
            'item_title' => ['nullable', 'string', 'max:500'],
            'day_number' => ['nullable', 'integer', 'min:0', 'max:255'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'is_completed' => ['nullable', 'boolean'],
            'completed_by_member_id' => ['nullable', 'integer', 'exists:members,id'],
        ]);
        $item = \App\Models\TaskChecklistItem::query()
            ->whereHas('task', static function (Builder $q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->findOrFail($checklistId);

        foreach ($payload as $k => $v) {
            if ($k === 'is_completed') {
                $item->toggleComplete((bool) $v, \App\Models\Member::query()->find($payload['completed_by_member_id'] ?? $actor->id));
            } else {
                $item->{$k} = $v;
            }
        }
        $item->updated_by_type = \App\Services\Construction\morph_type($actor);
        $item->updated_by_id = $actor->getKey();
        $item->save();

        return response()->json([
            'success' => true,
            'message' => 'Checklist item updated.',
            'data' => $item->fresh(),
        ]);
    }

    /**
     * Delete Task Checklist Item
     *
     * Removes an item from the task checklist.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param int $checklistId
     * @return JsonResponse
     */
    public function checklistDestroy(Request $request, Project $project, int $checklistId): JsonResponse
    {
        $actor = $request->user();
        if ($actor === null) {
            return $this->unauth();
        }
        $item = \App\Models\TaskChecklistItem::query()
            ->whereHas('task', static function (Builder $q) use ($project) {
                $q->where('project_id', $project->id);
            })
            ->findOrFail($checklistId);

        if (\Illuminate\Support\Carbon::now()->subDays(90)->greaterThan($item->created_at)) {
            $item->forceDelete();
        } else {
            $item->delete();
        }

        return response()->json(null, 204);
    }

    /**
     * Record Task Status Transition
     *
     * Transitions a task to a new status (e.g. in_progress, review, completed), logging GPS coordinates, progress percent, and verification files.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function recordStatusTransition(Request $request, Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $actor = $request->user();
        if (!$actor instanceof \App\Models\Member) {
            return response()->json([
                'success' => false,
                'error_code' => 'TASK_ACCESS_DENIED',
                'message' => 'Only members can submit task status transitions.',
            ], 403);
        }
        if (!$task->hasAssignee($actor->id)) {
            return response()->json([
                'success' => false,
                'error_code' => 'TASK_ACCESS_DENIED',
                'message' => 'You are not assigned to this task.',
            ], 403);
        }

        $rules = [
            'to_status' => ['required', 'string', Rule::in(['planned','pending','in_progress','review','completed','blocked','cancelled'])],
            'note' => ['nullable', 'string', 'max:5000'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'progress_percent' => ['nullable', 'integer', 'between:0,100'],
            'completed_qty' => ['nullable', 'numeric', 'min:0'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', new ValidMediaUpload()],
            'meta' => ['nullable', 'array'],
        ];
        try {
            $validated = $request->validate($rules);
            $transition = $this->tasks->transitionStatus(
                task: $task,
                toStatus: $validated['to_status'],
                actor: $actor,
                request: $request,
                options: $validated,
            );
            $transition = $this->appendSignedUrlsToDocuments($transition);
            $fresh = $this->appendSignedUrlsToDocuments($task->fresh($this->tasks->defaultIncludes()));
            return response()->json([
                'success' => true,
                'message' => 'Status transition recorded.',
                'data' => [
                    'transition' => $transition,
                    'task' => $fresh,
                ],
            ]);
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'error_code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        } catch (Throwable $e) {
            report($e);
            return response()->json([
                'success' => false,
                'error_code' => $e->getCode() === 403 ? 'GPS_REQUIRED' : 'INTERNAL_ERROR',
                'message' => config('app.debug') ? $e->getMessage() : 'Status transition failed.',
            ], $e->getCode() === 403 ? 403 : 500);
        }
    }

    /**
     * List Task Status Transitions
     *
     * Retrieves audit trail of status transitions and comments on a task.
     *
     * @tags Project Tasks & Execution
     * @param Project $project
     * @param Task $task
     * @return JsonResponse
     */
    public function transitionIndex(Project $project, Task $task): JsonResponse
    {
        $this->assertBelongsToProject($task, $project);
        $paginated = $task->statusTransitions()->with(['commenter','documents'])->latest('id')->paginate(25);
        $paginated = $this->appendSignedUrlsToDocuments($paginated);
        return response()->json([
            'success' => true,
            'data' => $paginated,
        ]);
    }

    /**
     * Sync Task Changes (Delta Sync)
     *
     * Returns task modifications and delta changes since a specified timestamp for offline sync.
     *
     * @tags Project Tasks & Execution
     * @param Request $request
     * @param Project $project
     * @return JsonResponse
     */
    public function delta(Request $request, Project $project): JsonResponse
    {
        $request->validate(['since' => 'required|date']);
        $since = \Illuminate\Support\Carbon::parse((string) $request->input('since'));
        return response()->json([
            'success' => true,
            'data' => $this->tasks->deltaSince($project, $since),
        ]);
    }

    private function assertBelongsToProject(Task $task, Project $project): void
    {
        if ((int) $task->project_id !== (int) $project->id) {
            abort(response()->json([
                'success' => false,
                'error_code' => 'TASK_ACCESS_DENIED',
                'message' => 'Task does not belong to this project.',
            ], 403));
        }
    }

    private function unauth(): JsonResponse
    {
        return response()->json([
            'success' => false,
            'error_code' => 'AUTH_REQUIRED',
            'message' => 'Authentication required.',
        ], 401);
    }

    private function appendSignedUrlsToDocuments(mixed $data): mixed
    {
        if ($data instanceof \App\Models\ConstructionDocument) {
            if (method_exists($data, 'append')) {
                $data->append('signed_view_url');
            }
            return $data;
        }
        if ($data instanceof \Illuminate\Database\Eloquent\Model) {
            foreach ($data->getRelations() as $key => $relation) {
                $data->setRelation($key, $this->appendSignedUrlsToDocuments($relation));
            }
            foreach (class_uses_recursive($data) as $trait) {
                if (method_exists($data, 'getLoadedMorphToRelations')) {
                    foreach ($data->getLoadedMorphToRelations() ?? [] as $mkey => $mval) {
                        if (is_object($mval)) {
                            $data->setRelation($mkey, $this->appendSignedUrlsToDocuments($mval));
                        }
                    }
                }
            }
            return $data;
        }
        if ($data instanceof \Illuminate\Pagination\LengthAwarePaginator) {
            $items = $data->getCollection()->map(fn ($item) => $this->appendSignedUrlsToDocuments($item));
            $data->setCollection($items);
            return $data;
        }
        if ($data instanceof \Illuminate\Pagination\Paginator) {
            $items = $data->getCollection()->map(fn ($item) => $this->appendSignedUrlsToDocuments($item));
            $data->setCollection($items);
            return $data;
        }
        if ($data instanceof \Illuminate\Support\Enumerable) {
            return $data->map(fn ($item) => $this->appendSignedUrlsToDocuments($item));
        }
        if (is_array($data)) {
            return array_map(fn ($item) => $this->appendSignedUrlsToDocuments($item), $data);
        }
        return $data;
    }
}
