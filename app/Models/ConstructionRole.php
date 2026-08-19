<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Permission;

use App\Models\MemberRoleAssignment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionRole extends Model
{
    use SoftDeletes;

    protected $table = 'construction_roles';

    protected $fillable = [
        'company_id',
        'name',
        'slug',
        'description',
        'is_system_role',
        'status',
    ];

    protected $casts = [
        'is_system_role' => 'boolean',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(
            Permission::class,
            'construction_role_permissions',
            'role_id',
            'permission_id'
        )->withTimestamps();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(MemberRoleAssignment::class);
    }
}
