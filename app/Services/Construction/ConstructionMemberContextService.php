<?php

namespace App\Services\Construction;

use App\Models\Project;
use App\Models\ConstructionRole;
use App\Models\Member;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

class ConstructionMemberContextService
{
    public function __construct(
        private readonly ConstructionAuthorizationService $authorization
    ) {
    }

    public function resolve(
        Member $member,
        ?string $requestedRole = null,
        ?int $requestedProjectId = null
    ): array {
        $projects = $this->authorization->getProjects($member);

        $activeProject = $this->resolveProject(
            $projects,
            $requestedProjectId
        );

        $activeRole = $this->resolveActiveRole(
            $member,
            $requestedRole,
            $activeProject
        );

        /*
         * Roles available for the current context.
         *
         * Project context:
         *   only roles assigned to the selected project.
         *
         * No project context:
         *   only genuine global roles.
         */
        $availableRoles = $activeProject !== null
            ? $this->authorization->getRoles(
                $member,
                $activeProject->getKey()
            )
            : $this->authorization->getGlobalRoles($member);

        /*
         * Permissions belong to the active role only.
         *
         * This prevents permissions from multiple roles being merged
         * into one effective context.
         */
        $permissions = $activeRole !== null
            ? $this->authorization->getPermissionsForRole(
                $member,
                $activeRole,
                $activeProject?->getKey()
            )
            : [];

        return [
            'member' => $member,

            // All active construction roles assigned to the member.
            'roles' => $this->authorization->getRoles($member),

            // Roles valid for the current project/global context.
            'available_roles' => $availableRoles,

            // All accessible construction projects.
            'projects' => $projects,

            // Effective permissions of the active role.
            'permissions' => $permissions,

            // Currently selected role.
            'active_role' => $activeRole,

            // Currently selected project.
            'active_project' => $activeProject,
        ];
    }

    public function getWebContext(
        Member $member,
        ?string $requestedRole = null,
        ?int $requestedProjectId = null
    ): array {
        return $this->resolve(
            $member,
            $requestedRole,
            $requestedProjectId
        );
    }

    public function getMobileContext(
        Member $member,
        ?string $requestedRole = null,
        ?int $requestedProjectId = null
    ): array {
        return $this->resolve(
            $member,
            $requestedRole,
            $requestedProjectId
        );
    }

    private function resolveProject(
        Collection $projects,
        ?int $requestedProjectId
    ): ?Project {
        if ($requestedProjectId !== null) {
            $project = $projects->firstWhere(
                'id',
                $requestedProjectId
            );

            if ($project === null) {
                throw new AuthorizationException(
                    'Requested project is not accessible.'
                );
            }

            return $project;
        }

        // Automatically select the only accessible project.
        return $projects->count() === 1
            ? $projects->first()
            : null;
    }

    private function resolveActiveRole(
        Member $member,
        ?string $requestedRole,
        ?Project $activeProject
    ): ?Role {
        /*
         * Project context:
         * the role must be assigned to the selected project.
         */
        if ($activeProject !== null) {
            $projectRoles = $this->authorization->getRoles(
                $member,
                $activeProject->getKey()
            );

            if ($requestedRole === null) {
                return $projectRoles->count() === 1
                    ? $projectRoles->first()
                    : null;
            }

            $role = $projectRoles
                ->where('slug', $requestedRole)
                ->first();

            if ($role === null) {
                throw new AuthorizationException(
                    'Requested role is not valid for the active project.'
                );
            }

            return $role;
        }

        /*
         * No project context:
         * only global role assignments may be selected.
         */
        $globalRoles = $this->authorization->getGlobalRoles($member);

        if ($requestedRole === null) {
            return $globalRoles->count() === 1
                ? $globalRoles->first()
                : null;
        }

        $role = $globalRoles
            ->where('slug', $requestedRole)
            ->first();

        if ($role === null) {
            throw new AuthorizationException(
                'Requested role requires a project context.'
            );
        }

        return $role;
    }
}