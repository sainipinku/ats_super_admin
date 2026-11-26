<?php

namespace App\Http\Controllers\Concerns;

use App\Models\ExecutionPlan;
use App\Models\ExecutionTask;
use App\Models\Task;
use App\Services\Construction\TaskMirrorSyncService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait ResolvesProjectDynamicTasks
{
    /**
     * Safely resolve a string from a scalar, BackedEnum, UnitEnum, or Stringable object.
     */
    public function stringifyTaskField(mixed $value, string $default = ''): string
    {
        if ($value === null) {
            return $default;
        }

        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof \UnitEnum) {
            return $value->name;
        }

        if (is_object($value)) {
            if (isset($value->value)) {
                return (string) $value->value;
            }
            if (method_exists($value, '__toString')) {
                return (string) $value;
            }
            return $default;
        }

        if (is_scalar($value)) {
            return (string) $value;
        }

        return $default;
    }

    /**
     * Safely normalize a task priority to an allowed string value.
     */
    public function normalizeTaskPriority(mixed $priority, string $default = 'medium'): string
    {
        $raw = strtolower(trim($this->stringifyTaskField($priority, $default)));
        return in_array($raw, ['low', 'medium', 'high', 'critical'], true) ? $raw : $default;
    }

    /**
     * Resolve and merge all dynamic execution tasks for the given projects,
     * unifying both legacy ExecutionTask and unified Task records.
     * Ensures an ExecutionTask record exists for foreign key referential integrity.
     */
    public function getDynamicTasksForProjects(Collection|array $projectIds): Collection
    {
        $ids = collect($projectIds)
            ->filter(fn ($id) => !empty($id) && is_numeric($id) && (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if (empty($ids)) {
            return collect();
        }

        try {
            // 1. Fetch all existing ExecutionTasks
            $executionTasks = ExecutionTask::whereIn('project_id', $ids)
                ->where('status', '!=', 'cancelled')
                ->orderBy('task_code')
                ->get(['id', 'project_id', 'task_code', 'title', 'status', 'priority']);

            // 2. Fetch all unified Tasks
            $unifiedTasks = Task::whereIn('project_id', $ids)
                ->where('status', '!=', 'cancelled')
                ->orderBy('task_code')
                ->get([
                    'id',
                    'project_id',
                    'task_code',
                    'title',
                    'status',
                    'priority',
                    'start_date',
                    'end_date',
                    'assigned_supervisor_member_id',
                    'member_id',
                    'progress_percent',
                ]);
        } catch (\Throwable $e) {
            report($e);
            return collect();
        }

        // Map existing ExecutionTasks by [project_id:task_code] and [project_id:normalized_title]
        $execByCode = [];
        $execByTitle = [];
        foreach ($executionTasks as $et) {
            if (!empty($et->task_code)) {
                $execByCode[$et->project_id . ':' . strtolower(trim((string) $et->task_code))] = $et;
            }
            if (!empty($et->title)) {
                $execByTitle[$et->project_id . ':' . strtolower(trim((string) $et->title))] = $et;
            }
        }

        // Identify unified tasks that do not yet have an ExecutionTask mirror
        $sync = app(TaskMirrorSyncService::class);
        $unifiedTasksNeedingMirror = [];
        foreach ($unifiedTasks as $ut) {
            $matched = $sync->findMatchingLegacyForUnified($ut);
            if ($matched === null) {
                $codeKey = !empty($ut->task_code) ? ($ut->project_id . ':' . strtolower(trim((string) $ut->task_code))) : null;
                $titleKey = !empty($ut->title) ? ($ut->project_id . ':' . strtolower(trim((string) $ut->title))) : null;
                $matched = ($codeKey && isset($execByCode[$codeKey]))
                    ? $execByCode[$codeKey]
                    : (($titleKey && isset($execByTitle[$titleKey])) ? $execByTitle[$titleKey] : null);
            }

            if ($matched) {
                $sync->syncTaskCodeIfMissing($matched, $ut);
                // If unified task has a nicer task_code or updated status, synchronize mirror
                $needsSave = false;
                if (empty($matched->task_code) && !empty($ut->task_code)) {
                    $matched->task_code = $ut->task_code;
                    $needsSave = true;
                }
                if (!empty($ut->status) && $matched->status !== $ut->status) {
                    $matched->status = $ut->status;
                    $needsSave = true;
                }
                if ($needsSave) {
                    try {
                        $matched->saveQuietly();
                    } catch (\Throwable) {
                    }
                }
            } else {
                $unifiedTasksNeedingMirror[] = $ut;
            }
        }

        // Safely mirror missing unified tasks into ExecutionTask via shared sync service
        if (!empty($unifiedTasksNeedingMirror)) {
            foreach ($unifiedTasksNeedingMirror as $ut) {
                try {
                    $mirror = $sync->ensureLegacyMirror($ut);
                    $codeKey = $mirror->project_id . ':' . strtolower(trim((string) $mirror->task_code));
                    $titleKey = $mirror->project_id . ':' . strtolower(trim((string) $mirror->title));
                    $execByCode[$codeKey] = $mirror;
                    $execByTitle[$titleKey] = $mirror;
                    $executionTasks->push($mirror);
                } catch (\Throwable $e) {
                    report($e);
                    $existing = $sync->findMatchingLegacyForUnified($ut);
                    if ($existing) {
                        $executionTasks->push($existing);
                    }
                }
            }
        }

        // Failsafe: Ensure EVERY unified task for these projects is included even if mirror creation was bypassed
        $existingCodes = $executionTasks->map(fn ($t) => $t->project_id . ':' . strtolower(trim((string) ($t->task_code ?? ''))))->filter()->all();
        $existingTitles = $executionTasks->map(fn ($t) => $t->project_id . ':' . strtolower(trim((string) ($t->title ?? ''))))->filter()->all();

        foreach ($unifiedTasks as $ut) {
            $codeKey = !empty($ut->task_code) ? ($ut->project_id . ':' . strtolower(trim((string) $ut->task_code))) : null;
            $titleKey = !empty($ut->title) ? ($ut->project_id . ':' . strtolower(trim((string) $ut->title))) : null;

            $alreadyRepresented = ($codeKey && in_array($codeKey, $existingCodes, true))
                || ($titleKey && in_array($titleKey, $existingTitles, true));

            if (!$alreadyRepresented) {
                $executionTasks->push((object) [
                    'id' => $ut->id,
                    'project_id' => $ut->project_id,
                    'task_code' => $this->stringifyTaskField($ut->task_code) ?: ('UTASK#' . $ut->id),
                    'title' => $this->stringifyTaskField($ut->title),
                    'status' => $this->stringifyTaskField($ut->status, 'planned'),
                    'priority' => $this->normalizeTaskPriority($ut->priority, 'medium'),
                ]);
            }
        }

        // Return deduplicated collection of tasks with consistent properties
        return $executionTasks->unique('id')->values()->map(function ($et) {
            return [
                'id' => $et->id,
                'project_id' => $et->project_id,
                'task_code' => $this->stringifyTaskField($et->task_code),
                'title' => $this->stringifyTaskField($et->title),
                'status' => $this->stringifyTaskField($et->status, 'planned'),
                'priority' => $this->normalizeTaskPriority($et->priority, 'medium'),
            ];
        });
    }

    /**
     * Resolves task_id from either ExecutionTask or Task, ensuring the returned ID
     * belongs to construction_execution_tasks and matches the given project.
     */
    public function resolveExecutionTaskId(?int $taskId, int $projectId): ?int
    {
        if (empty($taskId) || (int) $taskId <= 0) {
            return null;
        }

        $taskId = (int) $taskId;

        try {
            // 1. Direct ExecutionTask match on project
            $et = ExecutionTask::where('id', $taskId)->where('project_id', $projectId)->first();
            if ($et) {
                return $et->id;
            }

            // 2. Check unified Task match on project
            $ut = Task::where('id', $taskId)->where('project_id', $projectId)->first();
            if ($ut) {
                return app(TaskMirrorSyncService::class)->ensureLegacyMirror($ut)->id;
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return null;
    }
}

