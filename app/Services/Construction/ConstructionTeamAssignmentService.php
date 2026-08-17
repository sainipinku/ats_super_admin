<?php

namespace App\Services\Construction;

use App\Models\Construction\MemberRoleAssignment;
use App\Models\Construction\Project;
use App\Models\Construction\ProjectTeamMember;
use App\Models\Construction\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConstructionTeamAssignmentService
{
   
    public function assign(
        Project $project,
        array $validated,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use ($project, $validated, $actor) {
            $roleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;

            if ($roleId !== null) {
                $this->ensureRoleAssignable($roleId);
                $this->ensureNoDuplicate($project->id, (int) $validated['member_id'], $roleId);
            }

            $teamMember = ProjectTeamMember::create([
                'project_id' => $project->id,
                'member_id' => $validated['member_id'],
                'role_id' => $roleId,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $validated['status'] ?? 'active',
                'assigned_by_type' => $actor ? $actor::class : null,
                'assigned_by_id' => $actor?->getKey(),
            ]);

            if ($roleId !== null) {
                $this->syncRoleAssignment(
                    memberId: (int) $validated['member_id'],
                    roleId: $roleId,
                    projectId: $project->id,
                    status: $teamMember->status
                );
            }

            return $teamMember;
        });
    }

    public function update(
        Project $project,
        ProjectTeamMember $teamMember,
        array $validated,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use ($project, $teamMember, $validated, $actor) {
            $oldMemberId = (int) $teamMember->member_id;
            $oldRoleId = $teamMember->role_id ? (int) $teamMember->role_id : null;

            $newMemberId = (int) $validated['member_id'];
            $newRoleId = !empty($validated['role_id']) ? (int) $validated['role_id'] : null;

            if ($newRoleId !== null) {
                $this->ensureRoleAssignable($newRoleId);
                $this->ensureNoDuplicate($project->id, $newMemberId, $newRoleId, $teamMember->id);
            }

            $newStatus = $validated['status'] ?? $teamMember->status;

            $teamMember->update([
                'member_id' => $newMemberId,
                'role_id' => $newRoleId,
                'assigned_from' => $validated['assigned_from'] ?? null,
                'assigned_to' => $validated['assigned_to'] ?? null,
                'assignment_scope' => $validated['assignment_scope'] ?? null,
                'is_primary' => (bool) ($validated['is_primary'] ?? false),
                'status' => $newStatus,
            ]);

            // Deactivate the old authorization record when the role or member changed.
            if ($oldRoleId !== null && ($oldRoleId !== $newRoleId || $oldMemberId !== $newMemberId)) {
                $this->deactivateRoleAssignment($oldMemberId, $oldRoleId, $project->id);
            }

            // Create/reactivate the new authorization record.
            if ($newRoleId !== null) {
                $this->syncRoleAssignment($newMemberId, $newRoleId, $project->id, $newStatus);
            }

            return $teamMember;
        });
    }

    /**
     * Toggle the status of a role-bearing team assignment.
     *
     * Both ProjectTeamMember and MemberRoleAssignment are updated atomically.
     */
    public function toggleStatus(
        Project $project,
        ProjectTeamMember $teamMember,
        ?Model $actor
    ): ProjectTeamMember {
        return DB::transaction(function () use ($project, $teamMember, $actor) {
            $newStatus = $teamMember->status === 'active' ? 'inactive' : 'active';

            $teamMember->update(['status' => $newStatus]);

            if ($teamMember->role_id !== null) {
                $this->syncRoleAssignment(
                    memberId: (int) $teamMember->member_id,
                    roleId: (int) $teamMember->role_id,
                    projectId: $project->id,
                    status: $newStatus
                );
            }

            return $teamMember;
        });
    }

    /**
     * Remove a team assignment and deactivate the corresponding
     * MemberRoleAssignment.
     *
     * Only the specific assignment is removed. Other roles for the same
     * member/project remain untouched.
     */
    public function remove(
        Project $project,
        ProjectTeamMember $teamMember,
        ?Model $actor
    ): void {
        DB::transaction(function () use ($project, $teamMember, $actor) {
            $memberId = (int) $teamMember->member_id;
            $roleId = $teamMember->role_id ? (int) $teamMember->role_id : null;

            $teamMember->delete();

            if ($roleId !== null) {
                $this->deactivateRoleAssignment($memberId, $roleId, $project->id);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Private helpers
    |--------------------------------------------------------------------------
    */

    /**
     * Ensure the role exists, is active, and is not soft-deleted.
     */
    private function ensureRoleAssignable(int $roleId): void
    {
        $role = Role::withTrashed()->find($roleId);

        if (!$role || $role->trashed() || $role->status !== 'active') {
            throw ValidationException::withMessages([
                'role_id' => 'The selected role is not active or no longer exists.',
            ]);
        }
    }

    /**
     * Enforce composite uniqueness: (project_id, member_id, role_id).
     *
     * Same member + same project + different role = allowed.
     * Same member + same project + same role = rejected.
     */
    private function ensureNoDuplicate(int $projectId, int $memberId, int $roleId, ?int $ignoreId = null): void
    {
        $query = ProjectTeamMember::where('project_id', $projectId)
            ->where('member_id', $memberId)
            ->where('role_id', $roleId);

        if ($ignoreId !== null) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'role_id' => 'This member is already assigned this role on this project.',
            ]);
        }
    }

    /**
     * Create or reactivate the MemberRoleAssignment for the given
     * member/role/project, keeping its status synchronized with the
     * team assignment.
     */
    private function syncRoleAssignment(int $memberId, int $roleId, int $projectId, string $status): void
    {
        MemberRoleAssignment::updateOrCreate(
            [
                'member_id' => $memberId,
                'role_id' => $roleId,
                'project_id' => $projectId,
            ],
            [
                'status' => $status,
            ]
        );
    }

    /**
     * Deactivate the MemberRoleAssignment for the given member/role/project.
     *
     * The row is preserved (history is retained) but no longer grants
     * authorization because Phase 3 authorization requires status = active.
     */
    private function deactivateRoleAssignment(int $memberId, int $roleId, int $projectId): void
    {
        MemberRoleAssignment::where('member_id', $memberId)
            ->where('role_id', $roleId)
            ->where('project_id', $projectId)
            ->update(['status' => 'inactive']);
    }
}