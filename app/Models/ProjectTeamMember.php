<?php

namespace App\Models;

use App\Models\ConstructionRole;

use App\Models\Member;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProjectTeamMember extends Model
{
    protected $table = 'construction_project_team_members';

    protected $fillable = [
        'project_id',
        'member_id',
        'role_id',
        'assigned_from',
        'assigned_to',
        'assignment_scope',
        'is_primary',
        'status',
        'assigned_by_type',
        'assigned_by_id',
    ];

    protected $casts = [
        'assigned_from' => 'date',
        'assigned_to' => 'date',
        'is_primary' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(ConstructionRole::class);
    }

    public function assignedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function executionTaskAssignments(): HasMany
    {
        return $this->hasMany(
            ExecutionTaskAssignee::class,
            'member_id',
            'member_id'
        );
    }

    /**
     * Scope for the unique project/member/role combination.
     *
     * Same member + same project + same role = one assignment.
     * Same member + same project + different role = allowed.
     */
    public function scopeForAssignment(
        Builder $query,
        int $projectId,
        int $memberId,
        ?int $roleId
    ): Builder {
        return $query
            ->where('project_id', $projectId)
            ->where('member_id', $memberId)
            ->where('role_id', $roleId);
    }
}