<?php

namespace App\Models;

use App\Models\Construction\Project;
use App\Models\Construction\Role;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRoleAssignment extends Model
{
    protected $table = 'construction_member_role_assignments';

    protected $fillable = [
        'member_id',
        'role_id',
        'project_id',
        'status',
    ];

    protected $casts = [
        'status' => 'integer',
    ];

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}