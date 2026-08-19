<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MemberRoleAssignment extends Model
{
    protected $table = 'construction_member_role_assignments';

    protected $fillable = [
        'member_id',
        'role_id',
        'project_id',
        'is_senior',
        'can_self_draft',
    ];

    protected $casts = [
        'is_senior' => 'boolean',
        'can_self_draft' => 'boolean',
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
