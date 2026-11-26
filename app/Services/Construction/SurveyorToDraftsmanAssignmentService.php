<?php

namespace App\Services\Construction;

use App\Models\Company;
use App\Models\ConstructionRole;
use App\Models\Designation;
use App\Models\ExecutionTask;
use App\Models\ExecutionTaskAssignee;
use App\Models\DraftingJob;
use App\Models\Member;
use App\Models\MemberRoleAssignment;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\SurveySubmission;
use App\Models\SurveyVisit;
use App\Models\Task;
use App\Models\TaskAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Support\Construction\SurveyStatus;

class SurveyorToDraftsmanAssignmentService
{
    private const SURVEYOR_ROLE_SLUGS = ['surveyor', 'survey', 'field_surveyor'];
    private const DRAFTSMAN_ROLE_SLUGS = ['draft_person', 'draftsman', 'drafter', 'draft_man'];

    public function assign(
        Project $project,
        string $taskSource,
        int $taskId,
        int $memberId,
        ?string $dueDate,
        Model $actor,
        ?Request $request = null
    ): Model {
        $sync = app(TaskMirrorSyncService::class);

        return DB::transaction(function () use ($project, $taskSource, $taskId, $memberId, $dueDate, $actor, $request, $sync) {
            $task = match ($taskSource) {
                'execution' => ExecutionTask::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->find($taskId),
                'task' => Task::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->find($taskId),
                default => null,
            };

            if (!$task) {
                throw ValidationException::withMessages([
                    'task_id' => ['The selected task does not belong to this project.'],
                ]);
            }

            $counterpart = $task instanceof ExecutionTask
                ? $sync->findMatchingUnifiedForLegacy($task)
                : $sync->findMatchingLegacyForUnified($task);

            if ($this->hasActiveDraftsmanAssignment($task)
                || ($counterpart && $this->hasActiveDraftsmanAssignment($counterpart))) {
                throw ValidationException::withMessages([
                    'member_id' => ['This task already has an active Draftsman assignment.'],
                ]);
            }

            if (!$this->isReadyForDraftsmanAssignment($task)
                && (!$counterpart || !$this->isReadyForDraftsmanAssignment($counterpart))) {
                throw ValidationException::withMessages([
                    'task_id' => ['A Surveyor task must be completed before it can be assigned to a Draftsman.'],
                ]);
            }

            if (!$this->hasSurveyorAssignment($task, $project)
                && (!$counterpart || !$this->hasSurveyorAssignment($counterpart, $project))) {
                throw ValidationException::withMessages([
                    'task_id' => ['The completed task must have an assigned Surveyor before Draftsman assignment.'],
                ]);
            }

            $draftsman = Member::query()->find($memberId);
            if (!$draftsman || !$this->isAssignedDraftsman($project, $draftsman)) {
                throw ValidationException::withMessages([
                    'member_id' => ['Select an active Draftsman assigned to this project.'],
                ]);
            }

            $this->ensureDraftsmanOnProjectTeam($project, $draftsman, $actor);

            if ($task instanceof ExecutionTask) {
                $this->assignExecutionTask($task, $draftsman, $dueDate, $actor, $project, $request);
            } else {
                $this->assignUnifiedTask($task, $draftsman, $dueDate, $actor, $project, $request);
            }

            $sync->syncDraftsmanAssignmentToCounterpart($task, $draftsman, $dueDate, $actor, (int) $project->id);

            return $task->refresh();
        });
    }

    private function hasActiveDraftsmanAssignment(ExecutionTask|Task $task): bool
    {
        if (strtolower(trim((string) $task->status)) === 'drafting_in_progress') {
            return true;
        }

        if ($task instanceof ExecutionTask) {
            return $task->assignees()
                ->where('status', 'active')
                ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
                ->exists();
        }

        return $task->activeAssignments()
            ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
            ->exists();
    }

    private function isReadyForDraftsmanAssignment(ExecutionTask|Task $task): bool
    {
        $status = strtolower(trim((string) $task->status));

        return in_array($status, ['completed', 'survey_approved', 'review'], true);
    }

    private function hasSurveyorAssignment(ExecutionTask|Task $task, Project $project): bool
    {
        if ($task instanceof ExecutionTask) {
            $assignments = $task->assignees()
                ->where('status', 'active')
                ->with('member')
                ->get();

            foreach ($assignments as $assignment) {
                if ($this->isSurveyorRole($assignment->assignment_role)
                    || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS)) {
                    return true;
                }
            }

            if ($task->supervisor_member_id
                && $this->memberHasRole($project, (int) $task->supervisor_member_id, self::SURVEYOR_ROLE_SLUGS)) {
                return true;
            }

            return SurveyVisit::query()
                ->where('project_id', $project->id)
                ->where('execution_task_id', $task->id)
                ->exists();
        }

        foreach ($task->activeAssignments()->with('assignee')->get() as $assignment) {
            if ($this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS)) {
                return true;
            }
        }

        if ($task->assigned_supervisor_member_id
            && $this->memberHasRole($project, (int) $task->assigned_supervisor_member_id, self::SURVEYOR_ROLE_SLUGS)) {
            return true;
        }

        return SurveyVisit::query()
            ->where('project_id', $project->id)
            ->where('task_id', $task->id)
            ->exists();
    }

    private function assignExecutionTask(
        ExecutionTask $task,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project,
        ?Request $request
    ): void {
        $surveyorMemberIds = $task->assignees()
            ->where('status', 'active')
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS))
            ->pluck('member_id')
            ->values()
            ->all();

        $activeDraftsmanAssignment = $task->assignees()
            ->where('status', 'active')
            ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
            ->exists();

        if ($activeDraftsmanAssignment) {
            throw ValidationException::withMessages([
                'member_id' => ['This task already has an active Draftsman assignment.'],
            ]);
        }

        $task->assignees()
            ->where('status', 'active')
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->member_id, self::SURVEYOR_ROLE_SLUGS))
            ->each(fn ($assignment) => $assignment->update(['status' => 'inactive']));

        ExecutionTaskAssignee::updateOrCreate(
            [
                'execution_task_id' => $task->id,
                'member_id' => $draftsman->id,
            ],
            [
                'project_id' => $project->id,
                'assignment_role' => 'draft_person',
                'assigned_from' => now()->toDateString(),
                'assigned_to' => $dueDate,
                'is_primary' => true,
                'assigned_by_type' => $actor::class,
                'assigned_by_id' => $actor->getKey(),
                'status' => 'active',
            ]
        );

        $task->update(['status' => 'in_progress']);

        app(ConstructionActivityService::class)->log(
            module: 'execution_task_assignment',
            action: 'draftsman_assigned',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'task_source' => 'execution',
                'task_id' => $task->id,
                'previous_status' => 'completed',
                'new_status' => 'in_progress',
                'surveyor_member_ids' => $surveyorMemberIds,
                'draftsman_member_id' => $draftsman->id,
                'draftsman_name' => $draftsman->name,
                'assigned_at' => now()->toIso8601String(),
            ],
            request: $request
        );

        $this->createOrUpdateDraftingJob($task, $surveyorMemberIds, $draftsman, $dueDate, $actor, $project);
    }

    private function assignUnifiedTask(
        Task $task,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project,
        ?Request $request
    ): void {
        $surveyorMemberIds = $task->activeAssignments()
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS))
            ->pluck('assigned_to')
            ->values()
            ->all();

        $activeDraftsmanAssignment = $task->activeAssignments()
            ->whereIn('assignment_role', self::DRAFTSMAN_ROLE_SLUGS)
            ->exists();

        if ($activeDraftsmanAssignment) {
            throw ValidationException::withMessages([
                'member_id' => ['This task already has an active Draftsman assignment.'],
            ]);
        }

        $task->activeAssignments()
            ->get()
            ->filter(fn ($assignment) => $this->isSurveyorRole($assignment->assignment_role)
                || $this->memberHasRole($project, (int) $assignment->assigned_to, self::SURVEYOR_ROLE_SLUGS))
            ->each(fn ($assignment) => $assignment->update([
                'status' => 'completed',
                'is_transferred' => true,
            ]));

        $assignment = TaskAssignment::withTrashed()
            ->where('task_id', $task->id)
            ->where('assigned_to', $draftsman->id)
            ->first();

        if (!$assignment) {
            $assignment = new TaskAssignment([
                'uuid' => (string) \Illuminate\Support\Str::uuid(),
                'task_id' => $task->id,
                'project_id' => $project->id,
                'assigned_to' => $draftsman->id,
                'assigned_by' => $actor->getKey(),
            ]);
        } elseif ($assignment->trashed()) {
            $assignment->restore();
        }

        $assignment->fill([
            'project_id' => $project->id,
            'assignment_role' => 'draft_person',
            'assigned_from' => now()->toDateString(),
            'assigned_until' => $dueDate,
            'assigned_by' => $actor->getKey(),
            'assigned_by_type' => $actor::class,
            'assigned_by_uid' => $actor->getKey(),
            'status' => 'active',
            'is_primary' => true,
            'is_transferred' => false,
        ])->save();

        $task->update(['status' => 'in_progress']);

        app(ConstructionActivityService::class)->log(
            module: 'task_assignment',
            action: 'draftsman_assigned',
            actor: $actor,
            reference: $task,
            companyId: $project->company_id,
            projectId: $project->id,
            meta: [
                'task_source' => 'task',
                'task_id' => $task->id,
                'previous_status' => 'completed',
                'new_status' => 'in_progress',
                'surveyor_member_ids' => $surveyorMemberIds,
                'draftsman_member_id' => $draftsman->id,
                'draftsman_name' => $draftsman->name,
                'assigned_at' => now()->toIso8601String(),
            ],
            request: $request
        );

        $this->createOrUpdateDraftingJob($task, $surveyorMemberIds, $draftsman, $dueDate, $actor, $project);
    }

    private function createOrUpdateDraftingJob(
        ExecutionTask|Task $task,
        array $surveyorMemberIds,
        Member $draftsman,
        ?string $dueDate,
        Model $actor,
        Project $project
    ): void {
        $sync = app(TaskMirrorSyncService::class);
        $counterpart = $task instanceof ExecutionTask
            ? $sync->findMatchingUnifiedForLegacy($task)
            : $sync->findMatchingLegacyForUnified($task);

        $legacyId = $task instanceof ExecutionTask ? $task->id : $counterpart?->id;
        $unifiedId = $task instanceof Task ? $task->id : $counterpart?->id;

        $surveyorMemberId = (int) ($surveyorMemberIds[0]
            ?? ($task instanceof ExecutionTask ? $task->supervisor_member_id : $task->assigned_supervisor_member_id)
            ?? ($counterpart instanceof ExecutionTask ? $counterpart->supervisor_member_id : $counterpart?->assigned_supervisor_member_id));

        $surveyVisit = SurveyVisit::query()
            ->where('project_id', $project->id)
            ->where(function ($query) use ($legacyId, $unifiedId) {
                if ($legacyId !== null) {
                    $query->orWhere('execution_task_id', $legacyId)
                        ->orWhere('task_id', $legacyId);
                }
                if ($unifiedId !== null) {
                    $query->orWhere('task_id', $unifiedId)
                        ->orWhere('execution_task_id', $unifiedId);
                }
            })
            ->lockForUpdate()
            ->latest('id')
            ->first();

        if (!$surveyVisit) {
            $surveyVisit = SurveyVisit::create([
                'project_id' => $project->id,
                'survey_plan_id' => null,
                'task_id' => $unifiedId,
                'execution_task_id' => $legacyId,
                'member_id' => $surveyorMemberId,
                'checked_in_by_member_id' => $surveyorMemberId,
                'check_in_at' => now(),
                'check_out_at' => now(),
                'submitted_at' => now(),
                'status' => SurveyStatus::APPROVED,
            ]);
        } else {
            $visitPatch = [];
            if ($unifiedId !== null && (int) ($surveyVisit->task_id ?? 0) !== (int) $unifiedId) {
                $visitPatch['task_id'] = $unifiedId;
            }
            if ($legacyId !== null && (int) ($surveyVisit->execution_task_id ?? 0) !== (int) $legacyId) {
                $visitPatch['execution_task_id'] = $legacyId;
            }
            if ($visitPatch !== []) {
                $surveyVisit->update($visitPatch);
            }
        }

        $surveySubmission = SurveySubmission::query()
            ->where('survey_visit_id', $surveyVisit->id)
            ->latest('id')
            ->first();

        if (!$surveySubmission) {
            $surveySubmission = SurveySubmission::create([
                'project_id' => $project->id,
                'survey_visit_id' => $surveyVisit->id,
                'submitted_by_member_id' => $surveyVisit->checked_in_by_member_id,
                'submitted_at' => $surveyVisit->submitted_at ?? now(),
                'status' => SurveyStatus::APPROVED,
                'reviewed_by_member_id' => $actor instanceof Member ? $actor->getKey() : null,
                'reviewed_at' => now(),
            ]);
        } else {
            $surveySubmission->update([
                'status' => SurveyStatus::APPROVED,
                'reviewed_by_member_id' => $actor instanceof Member ? $actor->getKey() : null,
                'reviewed_at' => now(),
            ]);
        }

        DraftingJob::updateOrCreate(
            [
                'project_id' => $project->id,
                'survey_submission_id' => $surveySubmission->id,
            ],
            [
                'assigned_to_member_id' => $draftsman->id,
                'assigned_by_type' => $actor::class,
                'assigned_by_id' => $actor->getKey(),
                'assigned_at' => now(),
                'due_date' => $dueDate,
                'status' => 'in_progress',
            ]
        );

        $project->update(['current_stage' => 'drafting_in_progress']);
    }

    private function isAssignedDraftsman(Project $project, Member $member): bool
    {
        $activeTeamMember = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->exists();
        $hasProjectRoleAssignment = MemberRoleAssignment::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->exists();

        if (!$activeTeamMember && !$hasProjectRoleAssignment) {
            if (isset($member->status)) {
                $statusStr = strtolower(trim((string) $member->status));
                if ($statusStr === 'inactive' || $statusStr === 'disabled' || $statusStr === '2') {
                    return false;
                }
            }
            if (!$this->memberBelongsToProjectCompany($project, $member)) {
                return false;
            }
        }

        $teamRole = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $member->id)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS))
            ->exists();

        if ($teamRole) {
            return true;
        }

        $assignedRole = MemberRoleAssignment::query()
            ->where('member_id', $member->id)
            ->where('status', 1)
            ->where(function ($query) use ($project) {
                $query->where('project_id', $project->id)
                    ->orWhereNull('project_id');
            })
            ->whereHas('role', function ($query) {
                $query->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS)
                    ->orWhere('slug', 'like', '%draft%');
            })
            ->exists();

        if ($assignedRole) {
            return true;
        }

        return $this->memberHasDraftsmanRole($member);
    }

    private function memberBelongsToProjectCompany(Project $project, Member $member): bool
    {
        $company = $project->company ?: ($project->company_id ? Company::find($project->company_id) : null);
        $companyNames = [];
        if ($company) {
            if (!empty($company->name)) {
                $companyNames[] = strtolower(trim((string) $company->name));
            }
            if (!empty($company->legal_name)) {
                $companyNames[] = strtolower(trim((string) $company->legal_name));
            }
        }
        $companyNames = array_values(array_unique(array_filter($companyNames)));

        $memberCompany = strtolower(trim((string) ($member->company_name ?? '')));
        if ($memberCompany !== '' && !empty($companyNames)) {
            return in_array($memberCompany, $companyNames, true);
        }

        if ($project->company_id) {
            $companySuperAdminIds = SuperAdmin::query()
                ->where('company_id', $project->company_id)
                ->pluck('id')
                ->all();

            if (!empty($companySuperAdminIds) && in_array((int) $member->created_by, array_map('intval', $companySuperAdminIds), true)) {
                return true;
            }
        }

        return false;
    }

    private function memberHasDraftsmanRole(Member $member): bool
    {
        $draftRoleIds = Role::query()
            ->where(function ($q) {
                $q->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS)
                    ->orWhere('slug', 'like', '%draft%');
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $storedRoles = $member->getRawOriginal('roles');
        $storedRoles = is_array($storedRoles) ? $storedRoles : (json_decode((string) $storedRoles, true) ?: []);
        foreach ($storedRoles as $roleVal) {
            if (is_numeric($roleVal) && in_array((int) $roleVal, $draftRoleIds, true)) {
                return true;
            }
            if (is_string($roleVal)) {
                $norm = strtolower(str_replace(['-', ' '], '_', trim($roleVal)));
                if (in_array($norm, self::DRAFTSMAN_ROLE_SLUGS, true) || str_contains($norm, 'draft')) {
                    return true;
                }
            }
        }

        if (Member::query()
            ->whereKey($member->id)
            ->whereHas('roles', fn ($q) => $q->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS)->orWhere('slug', 'like', '%draft%'))
            ->exists()) {
            return true;
        }

        // Check member designation (string or array of IDs/strings)
        $desig = $member->designation;
        if (is_string($desig) && stripos($desig, 'draft') !== false) {
            return true;
        }
        if (is_array($desig) && !empty($desig)) {
            $numericIds = array_values(array_filter($desig, 'is_numeric'));
            if (!empty($numericIds)) {
                if (Designation::whereIn('id', $numericIds)->where('name', 'like', '%draft%')->exists()) {
                    return true;
                }
            }
            foreach ($desig as $item) {
                if (is_string($item) && stripos($item, 'draft') !== false) {
                    return true;
                }
            }
        }

        return false;
    }

    private function ensureDraftsmanOnProjectTeam(Project $project, Member $draftsman, Model $actor): void
    {
        $existing = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $draftsman->id)
            ->first();

        if ($existing) {
            if ($existing->status !== 'active') {
                $existing->update(['status' => 'active']);
            }
            return;
        }

        $draftRole = ConstructionRole::query()
            ->whereIn('slug', self::DRAFTSMAN_ROLE_SLUGS)
            ->where(function ($q) use ($project) {
                $q->where('company_id', $project->company_id)
                    ->orWhereNull('company_id');
            })
            ->orderByRaw('CASE WHEN company_id IS NOT NULL THEN 0 ELSE 1 END')
            ->first();

        if (!$draftRole) {
            $draftRole = ConstructionRole::create([
                'company_id' => $project->company_id,
                'name' => 'Draft Person',
                'slug' => 'draft_person',
                'description' => 'Drafting and CAD revisions',
                'is_system_role' => true,
                'status' => 'active',
            ]);
        }

        ProjectTeamMember::create([
            'project_id' => $project->id,
            'member_id' => $draftsman->id,
            'role_id' => $draftRole->id,
            'assignment_scope' => 'Draft Person',
            'assigned_by_type' => $actor::class,
            'assigned_by_id' => $actor->getKey(),
            'status' => 'active',
        ]);
    }

    private function memberHasRole(Project $project, int $memberId, array $slugs): bool
    {
        $activeTeamMember = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->exists();
        $hasProjectRoleAssignment = MemberRoleAssignment::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->exists();

        if (!$activeTeamMember && !$hasProjectRoleAssignment) {
            return false;
        }

        $teamRole = ProjectTeamMember::query()
            ->where('project_id', $project->id)
            ->where('member_id', $memberId)
            ->where('status', 'active')
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();

        if ($teamRole) {
            return true;
        }

        $constructionRole = MemberRoleAssignment::query()
            ->where('member_id', $memberId)
            ->where('status', 1)
            ->where(function ($query) use ($project, $activeTeamMember) {
                $query->where('project_id', $project->id);
                if ($activeTeamMember) {
                    $query->orWhereNull('project_id');
                }
            })
            ->whereHas('role', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();

        if ($constructionRole) {
            return true;
        }

        $legacyRoleIds = Role::query()->whereIn('slug', $slugs)->pluck('id')->all();
        if ($legacyRoleIds === []) {
            return false;
        }

        $member = Member::query()->find($memberId);
        $storedRoles = $member?->getRawOriginal('roles');
        $storedRoles = is_array($storedRoles) ? $storedRoles : (json_decode((string) $storedRoles, true) ?: []);
        if (array_intersect($legacyRoleIds, array_map('intval', $storedRoles)) !== []) {
            return true;
        }

        return Member::query()
            ->whereKey($memberId)
            ->whereHas('roles', fn ($query) => $query->whereIn('slug', $slugs))
            ->exists();
    }

    private function isSurveyorRole(mixed $assignmentRole): bool
    {
        $role = $assignmentRole instanceof \BackedEnum ? $assignmentRole->value : (string) $assignmentRole;

        return in_array(strtolower(trim($role)), self::SURVEYOR_ROLE_SLUGS, true);
    }
}
