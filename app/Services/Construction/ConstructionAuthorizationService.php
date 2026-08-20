<?php

namespace App\Services\Construction;

use App\Models\MemberRoleAssignment;
use App\Models\Permission;
use App\Models\Project;
use App\Models\ProjectTeamMember;
use App\Models\ConstructionRole;
use App\Models\Member;
use App\Models\SuperAdmin;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ConstructionAuthorizationService
{
    public function resolveActor(?Request $request = null): ?Model
    {
        foreach (['superadmin', 'admin', 'member', 'callingteam'] as $guard) {
            if (Auth::guard($guard)->check()) {
                /** @var Model $user */
                $user = Auth::guard($guard)->user();

                return $user;
            }
        }

        $requestUser = $request?->user();

        return $requestUser instanceof Model ? $requestUser : null;
    }

    /**
     * Get all effective construction permissions for the actor.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     *
     * @return array<int, string>
     */
    public function permissionsFor(
        ?Model $actor,
        ?int $projectId = null
    ): array {
        if (!$actor) {
            return [];
        }

        if ($actor instanceof SuperAdmin) {
            return Permission::query()
                ->orderBy('slug')
                ->pluck('slug')
                ->all();
        }

        if (!$actor instanceof Member) {
            return [];
        }

        $query = Permission::query()
            ->select('construction_permissions.slug')
            ->join(
                'construction_role_permissions',
                'construction_role_permissions.permission_id',
                '=',
                'construction_permissions.id'
            )
            ->join(
                'construction_roles',
                'construction_roles.id',
                '=',
                'construction_role_permissions.role_id'
            )
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $actor->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        $this->applyProjectScope($query, $projectId);

        return $query
            ->orderBy('construction_permissions.slug')
            ->pluck('construction_permissions.slug')
            ->all();
    }

    /**
     * Check whether the actor has at least one requested permission.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     *
     * @param array<int, string> $permissions
     */
    public function hasAnyPermission(
        ?Model $actor,
        array $permissions,
        ?int $projectId = null
    ): bool {
        if (!$actor || $permissions === []) {
            return false;
        }

        if ($actor instanceof SuperAdmin) {
            return true;
        }

        if (!$actor instanceof Member) {
            return false;
        }

        $query = Permission::query()
            ->join(
                'construction_role_permissions',
                'construction_role_permissions.permission_id',
                '=',
                'construction_permissions.id'
            )
            ->join(
                'construction_roles',
                'construction_roles.id',
                '=',
                'construction_role_permissions.role_id'
            )
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $actor->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->whereIn(
                'construction_permissions.slug',
                $permissions
            );

        $this->applyProjectScope($query, $projectId);

        return $query->exists();
    }

    public function can(
        Member $member,
        string $permission,
        ?Project $project = null
    ): bool {
        return $this->hasAnyPermission(
            $member,
            [$permission],
            $project?->getKey()
        );
    }

    /**
     * Get active roles for the member.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     */
    public function getRoles(
        Member $member,
        ?int $projectId = null
    ): Collection {
        $query = ConstructionRole::query()
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $member->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        $this->applyProjectScope($query, $projectId);

        return $query->get(['construction_roles.*']);
    }

    /**
     * Get active global roles (project_id IS NULL).
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     */
   public function getGlobalRoles(Member $member): Collection
{
    return ConstructionRole::query()
        ->whereHas('assignments', function ($query) use ($member) {
            $query
                ->where('member_id', $member->getKey())
                ->where('status', 1)
                ->whereNull('project_id');
        })
        ->where('status', 'active')
        ->whereNull('deleted_at')
        ->get();
}
    /**
     * Get projects accessible through active role assignments
     * or active project-team membership.
     */
    public function getProjects(Member $member): Collection
    {
        $projectIds = MemberRoleAssignment::query()
            ->where(
                'member_id',
                $member->getKey()
            )
            ->where(
                'status',
                1
            )
            ->whereNotNull('project_id')
            ->pluck('project_id')
            ->merge(
                ProjectTeamMember::query()
                    ->where(
                        'member_id',
                        $member->getKey()
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->pluck('project_id')
            )
            ->unique()
            ->values();

        if ($projectIds->isEmpty()) {
            return new Collection();
        }

        return Project::query()
            ->whereIn('id', $projectIds)
            ->get();
    }

    /**
     * @return array<int, string>
     */
    public function getPermissions(
        Member $member,
        ?int $projectId = null
    ): array {
        return $this->permissionsFor(
            $member,
            $projectId
        );
    }

    /**
     * Get permissions for one specific active member role.
     *
     * MemberRoleAssignment.status:
     * 1 = active
     * 0 = inactive
     *
     * @return array<int, string>
     */
    public function getPermissionsForRole(
        Member $member,
        ConstructionRole $role,
        ?int $projectId = null
    ): array {
        $query = Permission::query()
            ->select('construction_permissions.slug')
            ->join(
                'construction_role_permissions',
                'construction_role_permissions.permission_id',
                '=',
                'construction_permissions.id'
            )
            ->join(
                'construction_roles',
                'construction_roles.id',
                '=',
                'construction_role_permissions.role_id'
            )
            ->join(
                'construction_member_role_assignments',
                'construction_member_role_assignments.role_id',
                '=',
                'construction_roles.id'
            )
            ->where(
                'construction_member_role_assignments.member_id',
                $member->getKey()
            )
            ->where(
                'construction_member_role_assignments.role_id',
                $role->getKey()
            )
            ->where(
                'construction_member_role_assignments.status',
                1
            )
            ->where(
                'construction_roles.status',
                'active'
            )
            ->whereNull('construction_roles.deleted_at')
            ->distinct();

        $this->applyProjectScope($query, $projectId);

        return $query
            ->orderBy('construction_permissions.slug')
            ->pluck('construction_permissions.slug')
            ->all();
    }

    public function resolveActiveRole(
        Member $member,
        ?string $requestedRole,
        ?int $projectId = null
    ): ?Role {
        $roles = $this->getRoles(
            $member,
            $projectId
        );

        if ($requestedRole === null) {
            return $roles->count() === 1
                ? $roles->first()
                : null;
        }

        return $roles
            ->where('slug', $requestedRole)
            ->first();
    }

    /**
     * Project-scoped checks require an exact project role assignment.
     *
     * Global assignments (project_id IS NULL) only apply
     * when there is no project context.
     */
    private function applyProjectScope(
        Builder $query,
        ?int $projectId
    ): void {
        if ($projectId === null) {
            return;
        }

        $query->where(
            'construction_member_role_assignments.project_id',
            $projectId
        );
    }

    public function inferProjectId(Request $request): ?int
    {
        $projectId = $request->integer('project_id');

        if ($projectId > 0) {
            return $projectId;
        }

        foreach (
            $request->route()?->parameters() ?? []
            as $parameter
        ) {
            if ($parameter instanceof Model) {
                if (
                    $parameter->getTable()
                    === 'construction_projects'
                ) {
                    return (int) $parameter->getKey();
                }

                $relatedProjectId =
                    $parameter->getAttribute('project_id');

                if ($relatedProjectId !== null) {
                    return (int) $relatedProjectId;
                }
            }
        }

        return null;
    }
}