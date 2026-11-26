<?php

namespace App\Services\Construction;

use App\Enums\TaskAssignmentStatus;
use App\Models\DailyProgressReport;
use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\Member;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use App\Models\TaskChecklist;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Shared cross-table resolution, mirroring, synchronization, and deduplication
 * between legacy ExecutionTask (construction_execution_tasks) and unified Task (tasks).
 */
class TaskMirrorSyncService
{
    public function normalizeCode(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }
        $trimmed = strtolower(trim($code));

        return $trimmed !== '' ? $trimmed : null;
    }

    public function stripMirrorSuffix(?string $code): ?string
    {
        $norm = $this->normalizeCode($code);
        if ($norm === null) {
            return null;
        }

        return preg_replace('/(?:-m\d+|-\d+)$/i', '', $norm) ?: $norm;
    }

    public function normalizeTitle(?string $title): string
    {
        return trim(strtolower((string) ($title ?? '')));
    }

    public function expectedFallbackCodeForUnifiedId(int $unifiedTaskId): string
    {
        return 'tsk-' . str_pad((string) $unifiedTaskId, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Extract the embedded unified task ID when a legacy mirror uses TSK-00016(-M1).
     */
    public function extractUnifiedIdFromFallbackCode(?string $code): ?int
    {
        $norm = $this->normalizeCode($code);
        if ($norm === null) {
            return null;
        }

        if (preg_match('/^tsk-0*(\d+)(?:-m\d+|-\d+)?$/i', $norm, $matches)) {
            $id = (int) $matches[1];

            return $id > 0 ? $id : null;
        }

        return null;
    }

    /**
     * Determine whether an ExecutionTask and a unified Task represent the same logical task.
     */
    public function areCounterparts(ExecutionTask $legacy, Task $unified): bool
    {
        if ($legacy->project_id !== null && $unified->project_id !== null
            && (int) $legacy->project_id !== (int) $unified->project_id) {
            return false;
        }

        $legacyCode = $this->normalizeCode($legacy->task_code);
        $unifiedCode = $this->normalizeCode($unified->task_code);

        if ($legacyCode !== null && $unifiedCode !== null) {
            if ($legacyCode === $unifiedCode) {
                return true;
            }
            if ($this->stripMirrorSuffix($legacyCode) === $this->stripMirrorSuffix($unifiedCode)) {
                return true;
            }
        }

        // Legacy mirror code matches TSK-000{unified.id} (or TSK-000{unified.id}-M1)
        $expectedCode = $this->expectedFallbackCodeForUnifiedId((int) $unified->id);
        if ($legacyCode !== null && (
            $legacyCode === $expectedCode
            || $this->stripMirrorSuffix($legacyCode) === $expectedCode
        )) {
            return true;
        }

        // Shared SurveyVisit linking both IDs
        if ($this->shareSurveyVisit($legacy, $unified)) {
            return true;
        }

        // Normalized title within the same project when explicit codes do not conflict
        $legacyTitle = $this->normalizeTitle($legacy->title);
        $unifiedTitle = $this->normalizeTitle($unified->title);
        if ($legacyTitle !== '' && $legacyTitle === $unifiedTitle) {
            if ($legacyCode !== null && $unifiedCode !== null && $legacyCode !== $unifiedCode) {
                return false;
            }

            return true;
        }

        return false;
    }

    public function findMatchingLegacyForUnified(Task $unified): ?ExecutionTask
    {
        $projectId = $unified->project_id ? (int) $unified->project_id : null;

        // 1. Direct SurveyVisit linkage
        if (Schema::hasTable('construction_survey_visits')
            && Schema::hasColumn('construction_survey_visits', 'execution_task_id')) {
            $linkedExecId = SurveyVisit::query()
                ->where('task_id', $unified->id)
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->whereNotNull('execution_task_id')
                ->orderByDesc('id')
                ->value('execution_task_id');

            if ($linkedExecId) {
                $byVisit = ExecutionTask::query()
                    ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                    ->find((int) $linkedExecId);
                if ($byVisit !== null) {
                    return $byVisit;
                }
            }
        }

        // 2. Match by non-empty task_code scoped to project when set
        if (!empty($unified->task_code)) {
            $byCode = ExecutionTask::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->where('task_code', $unified->task_code)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        // 3. Match by fallback mirror code TSK-000{id} (or TSK-000{id}-M*) within project
        $fallbackBase = 'TSK-' . str_pad((string) $unified->id, 5, '0', STR_PAD_LEFT);
        $byFallbackCode = ExecutionTask::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->where(function ($q) use ($fallbackBase) {
                $q->where('task_code', $fallbackBase)
                    ->orWhere('task_code', 'like', $fallbackBase . '-M%')
                    ->orWhere('task_code', 'like', $fallbackBase . '-%');
            })
            ->orderBy('id')
            ->first();
        if ($byFallbackCode !== null) {
            return $byFallbackCode;
        }

        // 4. Match by title in the same project
        if ($projectId !== null && !empty($unified->title)) {
            $normalizedTitle = $this->normalizeTitle($unified->title);
            $candidates = ExecutionTask::query()
                ->where('project_id', $projectId)
                ->whereRaw('LOWER(TRIM(title)) = ?', [$normalizedTitle])
                ->get();

            foreach ($candidates as $candidate) {
                if ($this->areCounterparts($candidate, $unified)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    public function findMatchingUnifiedForLegacy(ExecutionTask $legacy): ?Task
    {
        $projectId = $legacy->project_id ? (int) $legacy->project_id : null;

        // 1. Direct SurveyVisit linkage
        if (Schema::hasTable('construction_survey_visits')
            && Schema::hasColumn('construction_survey_visits', 'execution_task_id')) {
            $linkedTaskId = SurveyVisit::query()
                ->where('execution_task_id', $legacy->id)
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->whereNotNull('task_id')
                ->orderByDesc('id')
                ->value('task_id');

            if ($linkedTaskId) {
                $byVisit = Task::query()
                    ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                    ->find((int) $linkedTaskId);
                if ($byVisit !== null) {
                    return $byVisit;
                }
            }
        }

        // 2. Match by non-empty task_code scoped to project
        if (!empty($legacy->task_code)) {
            $byCode = Task::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->where('task_code', $legacy->task_code)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        // 3. Match by embedded ID in fallback code TSK-00016 -> Task #16
        $embeddedId = $this->extractUnifiedIdFromFallbackCode($legacy->task_code);
        if ($embeddedId !== null) {
            $byEmbedded = Task::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->find($embeddedId);
            if ($byEmbedded !== null && $this->areCounterparts($legacy, $byEmbedded)) {
                return $byEmbedded;
            }
        }

        // 4. Match by normalized title in the same project
        if ($projectId !== null && !empty($legacy->title)) {
            $normalizedTitle = $this->normalizeTitle($legacy->title);
            $candidates = Task::query()
                ->where('project_id', $projectId)
                ->whereRaw('LOWER(TRIM(title)) = ?', [$normalizedTitle])
                ->get();

            foreach ($candidates as $candidate) {
                if ($this->areCounterparts($legacy, $candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * Resolve paired [legacy, unified] models safely without colliding unrelated records that share an integer PK.
     *
     * @return array{legacy: ?ExecutionTask, unified: ?Task}
     */
    public function resolvePair(
        int $taskId,
        ?int $projectId = null,
        ?string $preferredSource = null,
        ?int $explicitLegacyId = null,
        ?int $explicitUnifiedId = null
    ): array {
        $legacy = null;
        $unified = null;

        if ($explicitLegacyId !== null && $explicitLegacyId > 0) {
            $legacy = ExecutionTask::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->find($explicitLegacyId);
        }
        if ($explicitUnifiedId !== null && $explicitUnifiedId > 0) {
            $unified = Task::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->find($explicitUnifiedId);
        }

        if ($legacy !== null && $unified !== null) {
            return ['legacy' => $legacy, 'unified' => $unified];
        }
        if ($legacy !== null) {
            return ['legacy' => $legacy, 'unified' => $this->findMatchingUnifiedForLegacy($legacy)];
        }
        if ($unified !== null) {
            return ['legacy' => $this->findMatchingLegacyForUnified($unified), 'unified' => $unified];
        }

        $source = $preferredSource ? strtolower(trim($preferredSource)) : null;

        if (in_array($source, ['legacy', 'execution'], true)) {
            $legacy = ExecutionTask::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->find($taskId);

            if ($legacy !== null) {
                return ['legacy' => $legacy, 'unified' => $this->findMatchingUnifiedForLegacy($legacy)];
            }
        }

        if (in_array($source, ['unified', 'task'], true)) {
            $unified = Task::query()
                ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
                ->find($taskId);

            if ($unified !== null) {
                return ['legacy' => $this->findMatchingLegacyForUnified($unified), 'unified' => $unified];
            }
        }

        $legacyCandidate = ExecutionTask::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->find($taskId);
        $unifiedCandidate = Task::query()
            ->when($projectId !== null, fn ($q) => $q->where('project_id', $projectId))
            ->find($taskId);

        if ($legacyCandidate !== null && $unifiedCandidate !== null) {
            if ($this->areCounterparts($legacyCandidate, $unifiedCandidate)) {
                return ['legacy' => $legacyCandidate, 'unified' => $unifiedCandidate];
            }

            // Different logical tasks happened to get the same auto-increment ID.
            // Resolve unified primary when ambiguous, or let resolvePairForMember disambiguate by assignee.
            return [
                'legacy' => $this->findMatchingLegacyForUnified($unifiedCandidate),
                'unified' => $unifiedCandidate,
            ];
        }

        if ($unifiedCandidate !== null) {
            return [
                'legacy' => $this->findMatchingLegacyForUnified($unifiedCandidate),
                'unified' => $unifiedCandidate,
            ];
        }

        if ($legacyCandidate !== null) {
            return [
                'legacy' => $legacyCandidate,
                'unified' => $this->findMatchingUnifiedForLegacy($legacyCandidate),
            ];
        }

        return ['legacy' => null, 'unified' => null];
    }

    /**
     * Resolve paired models in API member context, disambiguating ID collisions using member assignments.
     *
     * @return array{legacy: ?ExecutionTask, unified: ?Task}
     */
    public function resolvePairForMember(
        int $taskId,
        ?Member $member = null,
        ?string $preferredSource = null
    ): array {
        $source = $preferredSource ? strtolower(trim($preferredSource)) : null;
        if (in_array($source, ['legacy', 'execution', 'unified', 'task'], true)) {
            return $this->resolvePair($taskId, null, $source);
        }

        $legacyCandidate = ExecutionTask::query()->with('project')->find($taskId);
        $unifiedCandidate = Task::query()->with(['project', 'checklistItems'])->find($taskId);

        if ($legacyCandidate !== null && $unifiedCandidate !== null) {
            if ($this->areCounterparts($legacyCandidate, $unifiedCandidate)) {
                return ['legacy' => $legacyCandidate, 'unified' => $unifiedCandidate];
            }

            // Pick whichever candidate the member is directly assigned to first
            if ($member !== null) {
                $unifiedAssigned = $this->memberAssignedToUnified($unifiedCandidate, $member);
                $legacyAssigned = $this->memberAssignedToLegacy($legacyCandidate, $member);

                if ($legacyAssigned && !$unifiedAssigned) {
                    return [
                        'legacy' => $legacyCandidate,
                        'unified' => $this->findMatchingUnifiedForLegacy($legacyCandidate),
                    ];
                }
                if ($unifiedAssigned && !$legacyAssigned) {
                    return [
                        'legacy' => $this->findMatchingLegacyForUnified($unifiedCandidate),
                        'unified' => $unifiedCandidate,
                    ];
                }
            }

            return [
                'legacy' => $this->findMatchingLegacyForUnified($unifiedCandidate),
                'unified' => $unifiedCandidate,
            ];
        }

        if ($unifiedCandidate !== null) {
            return [
                'legacy' => $this->findMatchingLegacyForUnified($unifiedCandidate),
                'unified' => $unifiedCandidate,
            ];
        }

        if ($legacyCandidate !== null) {
            return [
                'legacy' => $legacyCandidate,
                'unified' => $this->findMatchingUnifiedForLegacy($legacyCandidate),
            ];
        }

        return ['legacy' => null, 'unified' => null];
    }

    /**
     * Guarantee a single ExecutionTask mirror exists for a unified Task and sync task_code consistently.
     */
    public function ensureLegacyMirror(Task $unified, ?int $preferredId = null): ExecutionTask
    {
        $existing = $this->findMatchingLegacyForUnified($unified);
        if ($existing !== null) {
            $this->syncTaskCodeIfMissing($existing, $unified);

            return $existing;
        }

        $planId = $this->resolveOrCreateExecutionPlanIdForProject((int) $unified->project_id);

        $targetCode = trim((string) ($unified->task_code ?? ''));
        if ($targetCode === '') {
            $targetCode = 'TSK-' . str_pad((string) $unified->id, 5, '0', STR_PAD_LEFT);
        }

        $baseCode = $targetCode;
        $counter = 1;
        while (ExecutionTask::where('task_code', $targetCode)->exists()) {
            $targetCode = $baseCode . '-M' . $counter;
            $counter++;
        }

        $useId = ($preferredId ?? $unified->id);
        $idPayload = ($useId > 0 && !ExecutionTask::where('id', $useId)->exists())
            ? ['id' => (int) $useId]
            : [];

        $priority = $unified->priority instanceof \BackedEnum
            ? (string) $unified->priority->value
            : (string) ($unified->priority ?? 'medium');
        $priority = in_array(strtolower($priority), ['low', 'medium', 'high', 'critical'], true)
            ? strtolower($priority)
            : 'medium';

        $legacy = DB::transaction(function () use ($unified, $planId, $targetCode, $idPayload, $priority) {
            $mirror = ExecutionTask::create(array_merge($idPayload, [
                'task_code' => $targetCode,
                'title' => (string) $unified->title,
                'description' => $unified->description,
                'project_id' => $unified->project_id,
                'execution_plan_id' => $planId,
                'priority' => $priority,
                'planned_start_date' => $unified->start_date ?? now(),
                'planned_end_date' => $unified->end_date ?? now()->addDays(1),
                'supervisor_member_id' => $unified->assigned_supervisor_member_id
                    ?? $unified->member_id
                    ?? null,
                'status' => $this->mapUnifiedStatusToLegacy((string) ($unified->status ?? 'pending')),
                'progress_percent' => (int) ($unified->progress_percent ?? 0),
            ]));

            $memberId = $unified->assigned_supervisor_member_id ?? $unified->member_id ?? null;
            if ($memberId !== null && $unified->project_id !== null) {
                ExecutionTaskAssignee::updateOrCreate(
                    [
                        'execution_task_id' => $mirror->id,
                        'project_id' => (int) $unified->project_id,
                        'member_id' => (int) $memberId,
                    ],
                    [
                        'assigned_at' => now(),
                        'status' => 'active',
                    ]
                );
            }

            $this->syncTaskCodeIfMissing($mirror, $unified, $targetCode);

            return $mirror;
        });

        return $legacy->loadMissing('project');
    }

    public function syncTaskCodeIfMissing(ExecutionTask $legacy, Task $unified, ?string $preferredCode = null): void
    {
        $legacyCode = trim((string) ($legacy->task_code ?? ''));
        $unifiedCode = trim((string) ($unified->task_code ?? ''));

        if ($unifiedCode === '' && ($preferredCode || $legacyCode !== '')) {
            $candidate = $preferredCode ?: $legacyCode;
            if ($candidate !== '' && !Task::withTrashed()->where('task_code', $candidate)->where('id', '!=', $unified->id)->exists()) {
                $unified->task_code = $candidate;
                $unified->saveQuietly();
            }
        } elseif ($legacyCode === '' && $unifiedCode !== '') {
            if (!ExecutionTask::where('task_code', $unifiedCode)->where('id', '!=', $legacy->id)->exists()) {
                $legacy->task_code = $unifiedCode;
                $legacy->saveQuietly();
            }
        }
    }

    /**
     * Propagate master-field changes from unified Task to its paired ExecutionTask mirror if one exists.
     */
    public function syncUnifiedToLegacy(Task $unified, ?ExecutionTask $legacy = null): void
    {
        $legacy ??= $this->findMatchingLegacyForUnified($unified);
        if ($legacy === null) {
            return;
        }

        $priority = $unified->priority instanceof \BackedEnum
            ? (string) $unified->priority->value
            : (string) ($unified->priority ?? 'medium');
        $priority = in_array(strtolower($priority), ['low', 'medium', 'high', 'critical'], true)
            ? strtolower($priority)
            : 'medium';

        $updates = [
            'title' => (string) $unified->title,
            'description' => $unified->description,
            'priority' => $priority,
            'planned_start_date' => $unified->start_date ? $unified->start_date->toDateString() : $legacy->planned_start_date,
            'planned_end_date' => $unified->end_date ? $unified->end_date->toDateString() : $legacy->planned_end_date,
            'planned_quantity' => $unified->planned_qty ?? $legacy->planned_quantity,
            'completed_quantity' => $unified->completed_qty ?? $legacy->completed_quantity,
            'unit' => $unified->qty_unit ?? $legacy->unit,
            'progress_percent' => $unified->progress_percent ?? $legacy->progress_percent,
            'supervisor_member_id' => $unified->assigned_supervisor_member_id ?? $legacy->supervisor_member_id,
            'status' => $this->mapUnifiedStatusToLegacy((string) ($unified->status ?? $legacy->status)),
        ];

        $legacy->update($updates);
        $this->syncTaskCodeIfMissing($legacy, $unified);
    }

    /**
     * Delete a paired ExecutionTask cleanly when the unified Task is deleted (and clean up references).
     */
    public function deleteLegacyCounterpart(ExecutionTask $legacy): void
    {
        DB::transaction(function () use ($legacy) {
            TaskChecklist::where('execution_task_id', $legacy->id)->delete();
            ExecutionTaskAssignee::where('execution_task_id', $legacy->id)->delete();
            DailyProgressReport::where('execution_task_id', $legacy->id)->update(['execution_task_id' => null]);
            ExecutionTask::where('parent_task_id', $legacy->id)->update(['parent_task_id' => null]);
            $legacy->delete();
        });
    }

    /**
     * Transfer/sync surveyor -> draftsman active assignment onto the counterpart model in the other table.
     */
    public function syncDraftsmanAssignmentToCounterpart(
        ExecutionTask|Task $primaryTask,
        Member $draftsman,
        ?string $dueDate,
        $actor,
        int $projectId
    ): void {
        if ($primaryTask instanceof ExecutionTask) {
            $unified = $this->findMatchingUnifiedForLegacy($primaryTask);
            if ($unified === null) {
                return;
            }

            $unified->activeAssignments()
                ->where('assigned_to', '!=', $draftsman->id)
                ->update([
                    'status' => 'completed',
                    'is_transferred' => true,
                ]);

            $assignment = TaskAssignment::withTrashed()
                ->where('task_id', $unified->id)
                ->where('assigned_to', $draftsman->id)
                ->first();

            if (!$assignment) {
                $assignment = new TaskAssignment([
                    'uuid' => (string) Str::uuid(),
                    'task_id' => $unified->id,
                    'project_id' => $projectId,
                    'assigned_to' => $draftsman->id,
                    'assigned_by' => $actor->getKey(),
                ]);
            } elseif ($assignment->trashed()) {
                $assignment->restore();
            }

            $assignment->fill([
                'project_id' => $projectId,
                'assignment_role' => 'draft_person',
                'assigned_from' => now()->toDateString(),
                'assigned_until' => $dueDate,
                'assigned_by' => $actor->getKey(),
                'assigned_by_type' => $actor::class,
                'assigned_by_uid' => $actor->getKey(),
                'status' => TaskAssignmentStatus::ACTIVE->value,
                'is_primary' => true,
                'is_transferred' => false,
            ])->save();

            $unified->update(['status' => 'in_progress']);
            $this->syncTaskCodeIfMissing($primaryTask, $unified);

            return;
        }

        // $primaryTask instanceof Task
        $legacy = $this->findMatchingLegacyForUnified($primaryTask);
        if ($legacy === null) {
            return;
        }

        $legacy->assignees()
            ->where('status', 'active')
            ->where('member_id', '!=', $draftsman->id)
            ->update(['status' => 'inactive']);

        ExecutionTaskAssignee::updateOrCreate(
            [
                'execution_task_id' => $legacy->id,
                'member_id' => $draftsman->id,
            ],
            [
                'project_id' => $projectId,
                'assignment_role' => 'draft_person',
                'assigned_from' => now()->toDateString(),
                'assigned_to' => $dueDate,
                'is_primary' => true,
                'assigned_by_type' => $actor::class,
                'assigned_by_id' => $actor->getKey(),
                'status' => 'active',
            ]
        );

        $legacy->update(['status' => 'in_progress']);
        $this->syncTaskCodeIfMissing($legacy, $primaryTask);
    }

    /**
     * Compute deduplicated workflowSummary task_counts and checklist_counts matching Show.jsx.
     *
     * @return array{task_counts: array<string,int>, checklist_counts: array<string,int>}
     */
    public function computeProjectSummaryCounts(
        Collection $executionTasks,
        Collection $unifiedTasks,
        Collection $surveyPlans
    ): array {
        $pairedLegacyIds = [];
        $logicalTasks = [];

        foreach ($unifiedTasks as $ut) {
            $matchedLegacy = null;
            foreach ($executionTasks as $et) {
                if (isset($pairedLegacyIds[$et->id])) {
                    continue;
                }
                if ($this->areCounterparts($et, $ut)) {
                    $matchedLegacy = $et;
                    $pairedLegacyIds[$et->id] = true;
                    break;
                }
            }

            $status = (string) ($ut->status ?? '');
            if ($matchedLegacy !== null) {
                // Pick fresher or more progressed status if legacy & unified drifted earlier
                $utUpdated = $ut->updated_at?->timestamp ?? 0;
                $etUpdated = $matchedLegacy->updated_at?->timestamp ?? 0;
                if ($etUpdated > $utUpdated || ($status === 'review' && $matchedLegacy->status === 'in_progress')) {
                    $status = (string) ($matchedLegacy->status ?? $status);
                }
            }

            $logicalTasks[] = [
                'status' => strtolower($status),
                'checklists' => $this->mergeUniqueChecklists(
                    $ut->checklistItems ?? collect(),
                    $matchedLegacy?->checklists ?? collect()
                ),
            ];
        }

        foreach ($executionTasks as $et) {
            if (isset($pairedLegacyIds[$et->id])) {
                continue;
            }
            $logicalTasks[] = [
                'status' => strtolower((string) ($et->status ?? '')),
                'checklists' => $this->mergeUniqueChecklists(
                    collect(),
                    $et->checklists ?? collect()
                ),
            ];
        }

        $totalTasks = count($logicalTasks);
        $completed = collect($logicalTasks)->where('status', 'completed')->count();
        $inProgress = collect($logicalTasks)->where('status', 'in_progress')->count();
        $pending = max(0, $totalTasks - $completed - $inProgress);

        $allTaskChecklists = collect($logicalTasks)->flatMap(fn ($row) => $row['checklists']);
        $planChecklists = $surveyPlans->flatMap(fn ($s) => $s->checklists ?? collect());

        return [
            'task_counts' => [
                'total' => $totalTasks,
                'completed' => $completed,
                'in_progress' => $inProgress,
                'pending' => $pending,
            ],
            'checklist_counts' => [
                'total' => $allTaskChecklists->count() + $planChecklists->count(),
                'completed' => $allTaskChecklists->where('is_completed', true)->count()
                    + $planChecklists->where('is_completed', true)->count(),
            ],
        ];
    }

    private function mergeUniqueChecklists(Collection $unifiedItems, Collection $legacyItems): Collection
    {
        $seen = [];
        $out = collect();

        foreach ($unifiedItems->concat($legacyItems) as $item) {
            $title = $this->normalizeTitle($item->item_title ?? $item->title ?? '');
            $day = (int) ($item->day_number ?? 1);
            $key = $title . ':' . $day;
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out->push([
                    'is_completed' => (bool) ($item->is_completed ?? false),
                ]);
            }
        }

        return $out;
    }

    private function shareSurveyVisit(ExecutionTask $legacy, Task $unified): bool
    {
        if (!Schema::hasTable('construction_survey_visits')
            || !Schema::hasColumn('construction_survey_visits', 'execution_task_id')) {
            return false;
        }

        if ($legacy->relationLoaded('surveyVisits') && $unified->relationLoaded('surveyVisits')) {
            $legacyVisitIds = $legacy->surveyVisits->pluck('id')->filter()->all();
            $unifiedVisitIds = $unified->surveyVisits->pluck('id')->filter()->all();

            if (array_intersect($legacyVisitIds, $unifiedVisitIds) !== []) {
                return true;
            }
        }

        return SurveyVisit::query()
            ->where('execution_task_id', $legacy->id)
            ->where('task_id', $unified->id)
            ->exists();
    }

    private function memberAssignedToUnified(Task $unified, Member $member): bool
    {
        $memberId = $member->getKey();
        if ((int) ($unified->assigned_supervisor_member_id ?? 0) === $memberId
            || (int) ($unified->member_id ?? 0) === $memberId) {
            return true;
        }

        return $unified->assignments()
            ->where('assigned_to', $memberId)
            ->whereNull('deleted_at')
            ->where(fn ($sq) => $sq->whereNull('is_transferred')->orWhere('is_transferred', 0))
            ->whereNotIn('status', ['revoked', 'transferred', 'cancelled'])
            ->exists();
    }

    private function memberAssignedToLegacy(ExecutionTask $legacy, Member $member): bool
    {
        $memberId = $member->getKey();
        if ((int) ($legacy->supervisor_member_id ?? 0) === $memberId) {
            return true;
        }

        return $legacy->assignees()
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();
    }

    private function mapUnifiedStatusToLegacy(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending' => 'planned',
            default => in_array(strtolower(trim($status)), ['planned', 'in_progress', 'review', 'completed', 'blocked', 'cancelled', 'survey_approved', 'drafting_in_progress'], true)
                ? strtolower(trim($status))
                : 'planned',
        };
    }

    private function resolveOrCreateExecutionPlanIdForProject(int $projectId): ?int
    {
        if ($projectId <= 0) {
            return null;
        }

        $existing = ExecutionPlan::query()
            ->where('project_id', $projectId)
            ->value('id');
        if ($existing !== null) {
            return (int) $existing;
        }

        try {
            return (int) ExecutionPlan::query()->create([
                'project_id' => $projectId,
                'plan_code' => 'EP-' . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT),
                'title' => 'Default Project Plan (auto)',
                'status' => 'approved',
            ])->getKey();
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
