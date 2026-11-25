<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Member extends Authenticatable
{
    use HasApiTokens, HasFactory, SoftDeletes, HasUuids, Notifiable;

    protected $fillable = [
        'uuid',
        'created_by',
        'assigned_admin_id',
        'is_calling_team',
        'name',
        'username',
        'email',
        'phone',
        'password',
        'must_change_password',
        'status',
        'roles',
        'departments',
        'designation',
        'slug',
        'otp',
        'otp_expire',
        'dob',
        'gender',
        'phone_verify_at',
        'image',
        'candidate_profile',
        'resume_path',
        'resume_original_name',
        'resume_mime',
        'resume_size',
        'resume_uploaded_at',
        'latitude',
        'longitude',
        'current_address',
        'remember_token',
        'reset_password_token',
        'reset_password_token_expires_at',
        'company_name',
        'state',
        'city',
        'registration_source',
        'approved_by',
        'approved_at',
        'approval_remark',
        'rejected_at',
    ];

    protected $casts = [
        'roles' => 'array',
        'departments' => 'array',
        'designation' => 'array',
        'otp_expire' => 'datetime',
        'dob' => 'date',
        'phone_verify_at' => 'datetime',
        'reset_password_token_expires_at' => 'datetime',
        'password' => 'hashed',
        'candidate_profile' => 'array',
        'resume_uploaded_at' => 'datetime',
        'resume_size' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'is_calling_team' => 'boolean',
        'must_change_password' => 'boolean',
        'status' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
        'location_captured_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    /**
     * Resolve a legacy system `roles.id` for a given slug, preferring database records before static fallbacks.
     */
    protected static function resolveSystemRoleIdForSlug(string $slug): ?int
    {
        $normalized = strtolower(trim($slug));
        if ($normalized === '') {
            return null;
        }

        $dbRoleId = Role::where('slug', $normalized)->value('id');
        if ($dbRoleId) {
            return (int) $dbRoleId;
        }

        $aliasSlugs = match ($normalized) {
            'super_admin', 'superadmin' => ['super_admin', 'superadmin'],
            'project_admin', 'project-admin' => ['project_admin', 'project-admin'],
            'driver', 'vehicle_driver', 'vehicle driver' => ['vehicle_driver', 'driver'],
            'draft_person', 'draftsman', 'draft man', 'draft_man' => ['draft_person', 'draftsman', 'draft_man'],
            default => [],
        };
        if (!empty($aliasSlugs)) {
            $aliasRoleId = Role::whereIn('slug', $aliasSlugs)->value('id');
            if ($aliasRoleId) {
                return (int) $aliasRoleId;
            }
        }

        return match ($normalized) {
            'super_admin', 'superadmin' => 2,
            'member' => 1,
            'admin' => 9,
            'project_admin', 'project-admin' => 3,
            'driver', 'vehicle_driver', 'vehicle driver' => 6,
            'surveyor' => 4,
            'draft_person', 'draftsman', 'draft man', 'draft_man' => 5,
            'review_approver' => 7,
            'site_employee' => 8,
            default => null,
        };
    }

    public function setRolesAttribute($value): void
    {
        $raw = is_array($value) ? $value : (is_string($value) ? (json_decode($value, true) ?: [$value]) : []);
        $cleanRoles = collect($raw)->map(function ($r) {
            if (is_numeric($r)) {
                return (int) $r;
            }
            if (is_string($r) && !empty(trim($r))) {
                return self::resolveSystemRoleIdForSlug($r);
            }
            return null;
        })->filter()->unique()->values()->all();

        $this->attributes['roles'] = json_encode($cleanRoles);
    }

    protected function hasRoleAssignmentHistory(): bool
    {
        if (!$this->exists || !$this->id) {
            return false;
        }

        try {
            return \App\Models\MemberRoleAssignment::where('member_id', $this->id)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    public function getRolesAttribute($value): array
    {
        $raw = is_array($value) ? $value : (json_decode($value ?? '', true) ?: []);

        $assignmentRoleIds = [];
        if ($this->exists && $this->id) {
            try {
                $assignments = \App\Models\MemberRoleAssignment::where('member_id', $this->id)
                    ->where('status', 1)
                    ->with('role')
                    ->get();

                foreach ($assignments as $assignment) {
                    if ($assignment->role && !empty($assignment->role->slug)) {
                        $roleId = self::resolveSystemRoleIdForSlug($assignment->role->slug);
                        if ($roleId) {
                            $assignmentRoleIds[] = $roleId;
                        }
                    }
                }
            } catch (\Throwable $e) {
                // Ignore query exceptions
            }
        }

        // Fallback: Only resolve role from designation for legacy members with no role assignment history
        $hasRoleAssignmentHistory = $this->hasRoleAssignmentHistory();

        if ($hasRoleAssignmentHistory) {
            // Active sub-roles are strictly governed by member_role_assignments; only preserve admin roles from raw JSON.
            $adminRoleSlugs = ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin'];
            try {
                $dbAdminRoleIds = Role::whereIn('slug', $adminRoleSlugs)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $dbNonAdminRoleIds = Role::whereNotIn('slug', $adminRoleSlugs)->pluck('id')->map(fn ($id) => (int) $id)->all();
                $allowedAdminRoleIds = array_values(array_unique(array_merge(
                    $dbAdminRoleIds,
                    array_diff([2, 3, 9], $dbNonAdminRoleIds)
                )));
            } catch (\Throwable) {
                $allowedAdminRoleIds = [2, 3, 9];
            }

            $raw = array_values(array_filter($raw, function ($entry) use ($allowedAdminRoleIds, $adminRoleSlugs) {
                if (is_numeric($entry)) {
                    return in_array((int) $entry, $allowedAdminRoleIds, true);
                }

                return is_string($entry) && in_array(strtolower(trim($entry)), $adminRoleSlugs, true);
            }));
        }

        if (empty($assignmentRoleIds) && !$hasRoleAssignmentHistory && !empty($this->designation)) {
            $desigs = is_array($this->designation) ? $this->designation : [$this->designation];
            foreach ($desigs as $d) {
                if (is_string($d) && str_contains(strtolower($d), 'driver')) {
                    $assignmentRoleIds[] = 6;
                } elseif (is_string($d) && str_contains(strtolower($d), 'surveyor')) {
                    $assignmentRoleIds[] = 4;
                } elseif (is_string($d) && (str_contains(strtolower($d), 'draft') || str_contains(strtolower($d), 'cad'))) {
                    $assignmentRoleIds[] = 5;
                }
            }
        }

        $allRoleIds = array_values(array_unique(array_filter(array_merge($assignmentRoleIds, $raw))));

        // If specific roles exist (like 6 for Driver), prefer them over generic fallback 1 (member)
        if (count($allRoleIds) > 1 && in_array(1, $allRoleIds, true)) {
            $specificRoles = array_values(array_diff($allRoleIds, [1]));
            return !empty($specificRoles) ? $specificRoles : $allRoleIds;
        }

        return !empty($allRoleIds) ? $allRoleIds : [1];
    }

    public function getIsDriverAttribute(): int
    {
        return $this->isDriver() ? 1 : 0;
    }

    public function isDriver(): bool
    {
        try {
            $driverRoleIds = \App\Models\ConstructionRole::where('slug', 'vehicle_driver')
                ->orWhere('name', 'Driver')
                ->pluck('id');

            if ($driverRoleIds->isNotEmpty()) {
                $isAssigned = \App\Models\MemberRoleAssignment::whereIn('role_id', $driverRoleIds)
                    ->where('member_id', $this->id)
                    ->where('status', 1)
                    ->exists();

                if ($isAssigned) {
                    return true;
                }
            }
        } catch (\Throwable) {
        }

        if (!empty($this->roles)) {
            $rolesArr = is_array($this->roles) ? $this->roles : [$this->roles];
            foreach ($rolesArr as $r) {
                if (is_string($r) && in_array(strtolower(trim($r)), ['vehicle_driver', 'driver', 'vehicle driver'], true)) {
                    return true;
                }
                if (is_numeric($r)) {
                    try {
                        $rName = \App\Models\Role::where('id', $r)->value('name');
                        if ($rName && str_contains(strtolower($rName), 'driver')) {
                            return true;
                        }
                    } catch (\Throwable) {
                    }
                }
            }
        }

        if (!$this->hasRoleAssignmentHistory() && !empty($this->designation)) {
            if (is_array($this->designation)) {
                foreach ($this->designation as $d) {
                    if (is_string($d) && str_contains(strtolower($d), 'driver')) {
                        return true;
                    }
                    if (is_numeric($d)) {
                        try {
                            $desigName = \App\Models\Designation::where('id', $d)->value('name');
                            if ($desigName && str_contains(strtolower($desigName), 'driver')) {
                                return true;
                            }
                        } catch (\Throwable) {
                        }
                    }
                }
            } elseif (is_string($this->designation) && str_contains(strtolower($this->designation), 'driver')) {
                return true;
            }
        }

        return false;
    }

    public const STATUS_PENDING = 0;
    public const STATUS_ACTIVE = 1;
    public const STATUS_REJECTED = 2;

    public function scopePending($query)
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeRejected($query)
    {
        return $query->where('status', self::STATUS_REJECTED);
    }

    public function scopeSelfRegistered($query)
    {
        return $query->whereIn('registration_source', ['web', 'mobile_api']);
    }

    public function isPending(): bool
    {
        return (int) $this->status === self::STATUS_PENDING;
    }

    public function isActive(): bool
    {
        return (int) $this->status === self::STATUS_ACTIVE;
    }

    public function isRejected(): bool
    {
        return (int) $this->status === self::STATUS_REJECTED;
    }

    public function approver()
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    public function uniqueIds()
    {
        return ['uuid'];
    }


    public function creator()
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(Member::class, 'assigned_admin_id');
    }

    public function callingTeamAssignments()
    {
        return $this->hasMany(JobApplication::class, 'assigned_calling_team_member_id');
    }


    protected $appends = [
        'profile_photo_url',
        'resume_url',
        'current_age',
        'department_names',
        'designation_names',
        'role_names',
        'employee_code',
        'is_driver',
        'is_admin',
        'assigned_roles',
        'assigned_role_names',
        'role_badges',
        'roles_formatted',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'member_roles', 'member_id', 'role_id');
    }

    /**
     * Scope to filter members by role ID
     */
    public function scopeHasRole($query, $roleId)
    {
        return $query->where(function($q) use ($roleId) {
            $q->whereJsonContains('roles', (int) $roleId)
              ->orWhereJsonContains('roles', (string) $roleId);
        });
    }

    /**
     * Scope to filter members by role slug
     */
    public function scopeHasRoleBySlug($query, $slug)
    {
        return $query->whereHas('roles', function($q) use ($slug) {
            $q->where('slug', $slug);
        });
    }

    public function dob(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                return $value ? Carbon::parse($value)->format('Y-m-d') : null;
            }
        );
    }

    public static function resolvePublicBaseUrl(): string
    {
        $base = config('app.url');

        if (empty($base) || $base === 'http://localhost' || $base === 'http://localhost/' || $base === 'https://localhost') {
            $envVal = env('APP_URL');
            if (!empty($envVal)) {
                $base = $envVal;
            }
        }

        // If still localhost or empty, check if incoming HTTP request has a live host
        if (empty($base) || str_contains($base, 'localhost') || str_contains($base, '127.0.0.1')) {
            try {
                if (function_exists('request') && request() && request()->getHttpHost() && !str_contains(request()->getHttpHost(), 'localhost') && !str_contains(request()->getHttpHost(), '127.0.0.1')) {
                    $base = request()->getSchemeAndHttpHost();
                }
            } catch (\Throwable) {
            }
        }

        if (empty($base)) {
            $base = 'http://localhost';
        }

        return rtrim($base, '/');
    }

    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->image)) {
                    if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                        return $this->image;
                    }

                    $cleanPath = ltrim((string) $this->image, '/');
                    if (str_starts_with($cleanPath, 'storage/')) {
                        $cleanPath = substr($cleanPath, 8);
                    }

                    $base = self::resolvePublicBaseUrl();
                    return $base . '/storage/' . $cleanPath;
                }

                $base = self::resolvePublicBaseUrl();
                return $base . '/images/profileimg.png';
            }
        );
    }

    public function resumeUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (empty($this->resume_path)) {
                    return null;
                }

                if (filter_var($this->resume_path, FILTER_VALIDATE_URL)) {
                    return $this->resume_path;
                }

                $cleanPath = ltrim((string) $this->resume_path, '/');
                if (str_starts_with($cleanPath, 'storage/')) {
                    $cleanPath = substr($cleanPath, 8);
                }

                $base = self::resolvePublicBaseUrl();
                return $base . '/storage/' . $cleanPath;
            }
        );
    }

    public function skills(): Attribute
    {
        return Attribute::make(
            get: function () {
                $candidate = is_array($this->candidate_profile) ? $this->candidate_profile : [];
                $skills = $candidate['skills'] ?? null;

                return is_array($skills) ? $skills : null;
            }
        );
    }

    public function experience(): Attribute
    {
        return Attribute::make(
            get: function () {
                $candidate = is_array($this->candidate_profile) ? $this->candidate_profile : [];

                return $candidate['experience'] ?? ($candidate['experience_label'] ?? null);
            }
        );
    }
   public function notify_tokens()
    {
        return $this->hasMany(FcmToken::class, 'user_id');
    }

    public function fcm_token()
    {
        return $this->hasOne(FcmToken::class,'user_id')->latestOfMany();
    }

    public function currentAge(): Attribute
    {
        return Attribute::make(
            get: function () {
                return $this->dob ? (int)Carbon::parse($this->dob)->diffInYears(Carbon::now()) : 0;
            }
        );
    }
    public function isAdmin(): bool
    {
        if (session()->has('impersonator')) {
            $assignedSlugs = $this->assigned_role_details['slugs'] ?? [];
            return in_array('admin', $assignedSlugs, true) || in_array('project_admin', $assignedSlugs, true) || in_array('super_admin', $assignedSlugs, true);
        }

        if ($this->slug === 'admin' || $this->slug === 'super-admin' || $this->slug === 'super_admin') {
            return true;
        }

        $assignedSlugs = $this->assigned_role_details['slugs'] ?? [];
        if (is_array($assignedSlugs) && !empty($assignedSlugs)) {
            if (in_array('admin', $assignedSlugs, true) || in_array('project_admin', $assignedSlugs, true) || in_array('super_admin', $assignedSlugs, true)) {
                return true;
            }
            return false;
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            try {
                $adminRoleIds = Role::whereIn('slug', ['admin', 'project_admin', 'super_admin'])->pluck('id')->toArray();
                if (!empty(array_intersect($this->roles, $adminRoleIds))) {
                    return true;
                }
            } catch (\Throwable) {
            }
            if (in_array('admin', $this->roles, true) || in_array('project_admin', $this->roles, true) || in_array('super_admin', $this->roles, true)) {
                return true;
            }
        }

        return false;
    }

    public function isSuperAdmin(): bool
    {
        if (session()->has('impersonator')) {
            return false;
        }

        if ($this->slug === 'super-admin' || $this->slug === 'super_admin') {
            return true;
        }

        $assignedSlugs = $this->assigned_role_details['slugs'] ?? [];
        if (is_array($assignedSlugs) && (in_array('super_admin', $assignedSlugs, true) || in_array('superadmin', $assignedSlugs, true))) {
            return true;
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            $superAdminRoleIds = Role::whereIn('slug', ['super-admin', 'super_admin', 'superadmin'])->pluck('id')->toArray();
            if (!empty(array_intersect($this->roles, $superAdminRoleIds))) {
                return true;
            }
            if (in_array('super_admin', $this->roles, true) || in_array('superadmin', $this->roles, true) || in_array('super-admin', $this->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if the role is a member role
     */
    public function isMember(): bool
    {
        return !$this->isAdmin() && !$this->isSuperAdmin();
    }

    public function isCallingTeam(): bool
    {
        return (bool) $this->is_calling_team;
    }

    public function getRoleName($roleId)
    {
        // Get role name from relationship
        return $this->roles()->where('id', $roleId)->value('name');
    }

    public function getGuardName(): string
    {
        if ($this->is_calling_team) {
            return 'callingteam';
        }

        return 'member';
    }

    /**
     * Get the dashboard route for this role
     */
    public function getDashboardRoute(): string
    {
        if ($this->is_calling_team) {
            return 'callingteam.dashboard';
        }

        return match ($this->id) {
            1 => 'admin.dashboard',
            3 => 'member.dashboard',
            default => 'home',
        };
    }
    public function employee()
    {
        return $this->hasOne(Employee::class, 'member_id');
    }

    public function latestLocation()
    {
        return $this->hasOne(EmployeeLocation::class, 'member_id')->latestOfMany('captured_at');
    }

    public function latestAttendanceLocation()
    {
        return $this->hasOne(AttendanceRecord::class, 'member_id')
            ->where(function ($query) {
                $query->where(function ($checkIn) {
                    $checkIn->whereNotNull('check_in_latitude')
                        ->whereNotNull('check_in_longitude')
                        ->whereBetween('check_in_latitude', [-90, 90])
                        ->whereBetween('check_in_longitude', [-180, 180]);
                })->orWhere(function ($checkOut) {
                    $checkOut->whereNotNull('check_out_latitude')
                        ->whereNotNull('check_out_longitude')
                        ->whereBetween('check_out_latitude', [-90, 90])
                        ->whereBetween('check_out_longitude', [-180, 180]);
                });
            })
            ->latestOfMany('attendance_date');
    }

    public function departmentList()
    {
        return $this->belongsToMany(Department::class, 'member_department', 'member_id', 'department_id')
            ->withTimestamps();
    }

    public function designationList()
    {
        return $this->belongsToMany(Designation::class, 'member_designation', 'member_id', 'designation_id')
            ->withTimestamps();
    }
    public function getDepartmentNamesAttribute()
    {
        if (is_array($this->departments)) {
            $ids = array_values(array_filter($this->departments, 'is_numeric'));
            $names = array_values(array_filter($this->departments, 'is_string'));
            if (!empty($ids)) {
                try {
                    $dbNames = Department::whereIn('id', $ids)->pluck('name')->toArray();
                    $names = array_merge($names, $dbNames);
                } catch (\Throwable) {
                }
            }
            return implode(', ', array_unique($names));
        }
        return is_string($this->departments) ? $this->departments : '';
    }
    public function getAssignedRoleDetailsAttribute(): array
    {
        try {
            $assignments = \App\Models\MemberRoleAssignment::where('member_id', $this->id)
                ->where('status', 1)
                ->with('role')
                ->get();
        } catch (\Throwable) {
            $assignments = collect();
        }

        $assignedSlugs = [];
        $assignedNames = [];

        foreach ($assignments as $assignment) {
            if ($assignment->role) {
                $slug = strtolower(trim($assignment->role->slug ?? ''));

                $formattedName = match ($slug) {
                    'surveyor' => 'Survey Man',
                    'vehicle_driver' => 'Driver',
                    'draft_person' => 'Draft Man',
                    'admin' => 'Admin',
                    'project_admin' => 'Project Admin',
                    'super_admin', 'superadmin' => 'Super Admin',
                    default => $assignment->role->name,
                };
                $assignedSlugs[] = $assignment->role->slug;
                $assignedNames[] = $formattedName;
            }
        }

        return [
            'slugs' => array_values(array_unique($assignedSlugs)),
            'names' => array_values(array_unique($assignedNames)),
        ];
    }

    public function getIsAdminAttribute(): bool
    {
        if (array_key_exists('is_admin', $this->attributes)) {
            return (bool) $this->attributes['is_admin'];
        }

        return $this->isAdmin();
    }

    public function getAssignedRolesAttribute(): array
    {
        if (array_key_exists('assigned_roles', $this->attributes) && is_array($this->attributes['assigned_roles'])) {
            return array_values($this->attributes['assigned_roles']);
        }

        $slugs = $this->assigned_role_details['slugs'] ?? [];
        if (!empty($slugs)) {
            return $slugs;
        }

        if ($this->isAdmin() || $this->slug === 'admin') {
            return ['admin'];
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            try {
                $sysRoleSlugs = Role::whereIn('id', $this->roles)->pluck('slug')->toArray();
                if (!empty($sysRoleSlugs)) {
                    return $sysRoleSlugs;
                }
            } catch (\Throwable) {
            }
        }

        return ['member'];
    }

    public function getAssignedRoleNamesAttribute(): array
    {
        $names = $this->assigned_role_details['names'] ?? [];
        if (!empty($names)) {
            return $names;
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            try {
                $systemRoles = Role::whereIn('id', $this->roles)->get();
                $mappedNames = [];
                foreach ($systemRoles as $sr) {
                    $srSlug = strtolower(trim($sr->slug ?? ''));
                    if (in_array($srSlug, ['admin', 'project_admin', 'super_admin'], true)) {
                        $mappedNames[] = $sr->name;
                    }
                }
                if (!empty($mappedNames)) {
                    return array_values(array_unique($mappedNames));
                }
            } catch (\Throwable) {
            }
        }

        if ($this->isAdmin() || $this->slug === 'admin') {
            return ['Admin'];
        }

        return ['Member'];
    }

    public function getRoleNamesAttribute()
    {
        $assignedNames = $this->assigned_role_names;
        if (!empty($assignedNames)) {
            return implode(', ', $assignedNames);
        }

        if ($this->isAdmin() || $this->slug === 'admin') {
            return 'Admin';
        }

        if (is_array($this->roles) && !empty($this->roles)) {
            try {
                return Role::whereIn('id', $this->roles)->pluck('name')->implode(', ');
            } catch (\Throwable) {
            }
        }

        return 'Member';
    }

    public function getDesignationNamesAttribute()
    {
        if (is_array($this->designation)) {
            $ids = array_values(array_filter($this->designation, 'is_numeric'));
            $names = array_values(array_filter($this->designation, 'is_string'));
            if (!empty($ids)) {
                try {
                    $dbNames = Designation::whereIn('id', $ids)->pluck('name')->toArray();
                    $names = array_merge($names, $dbNames);
                } catch (\Throwable) {
                }
            }
            return implode(', ', array_unique($names));
        }
        return is_string($this->designation) ? $this->designation : '';
    }

    public function getRoleBadgesAttribute(): array
    {
        if (array_key_exists('role_badges', $this->attributes) && is_array($this->attributes['role_badges'])) {
            return array_values($this->attributes['role_badges']);
        }

        $badges = [];

        $adminSlugs = ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin'];
        $superAdminSlugs = ['super_admin', 'superadmin'];
        $adminOnlySlugs = ['admin', 'project_admin', 'project-admin'];
        try {
            $rolesMeta = Role::get(['id', 'slug']);
            $superIds = $rolesMeta
                ->filter(fn ($r) => in_array(strtolower(trim((string) $r->slug)), $superAdminSlugs, true))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $adminOnlyIds = $rolesMeta
                ->filter(fn ($r) => in_array(strtolower(trim((string) $r->slug)), $adminOnlySlugs, true))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $otherIds = $rolesMeta
                ->filter(fn ($r) => !in_array(strtolower(trim((string) $r->slug)), $adminSlugs, true))
                ->pluck('id')->map(fn ($id) => (int) $id)->all();
            $superAdminRoleIds = array_values(array_unique(array_merge($superIds, array_diff([2], $otherIds, $adminOnlyIds))));
            $adminOnlyRoleIds = array_values(array_unique(array_merge($adminOnlyIds, array_diff([3, 9], $otherIds, $superIds))));
        } catch (\Throwable) {
            $superAdminRoleIds = [2];
            $adminOnlyRoleIds = [3, 9];
        }

        // 1. Super Admin
        $memberRoles = is_array($this->roles) ? $this->roles : [];
        $isSuperAdmin = $this->slug === 'super-admin'
            || collect($memberRoles)->contains(function ($r) use ($superAdminRoleIds, $superAdminSlugs) {
                if (is_numeric($r)) {
                    return in_array((int) $r, $superAdminRoleIds, true);
                }

                return is_string($r) && in_array(strtolower(trim($r)), $superAdminSlugs, true);
            });
        if ($isSuperAdmin) {
            $badges[] = 'Super Admin';
        }

        // 2. Admin (skip redundant Admin badge for Super Admin accounts)
        $isAdmin = !$isSuperAdmin && (
            $this->isAdmin()
            || $this->slug === 'admin'
            || collect($memberRoles)->contains(function ($r) use ($adminOnlyRoleIds, $adminOnlySlugs) {
                if (is_numeric($r)) {
                    return in_array((int) $r, $adminOnlyRoleIds, true);
                }

                return is_string($r) && in_array(strtolower(trim($r)), $adminOnlySlugs, true);
            })
        );
        if ($isAdmin) {
            $badges[] = 'Admin';
        }

        // 3. Assigned roles from MemberRoleAssignment
        $hasAssignmentHistory = $this->hasRoleAssignmentHistory();
        if (!empty($this->assigned_role_names)) {
            foreach ($this->assigned_role_names as $rName) {
                if (!empty($rName)) {
                    $badges[] = trim($rName);
                }
            }
        }

        // 4. Mapped from legacy system `roles` column (only admin roles if assignment history exists)
        if (is_array($this->roles) && !empty($this->roles)) {
            $numericIds = array_values(array_filter($this->roles, 'is_numeric'));
            if (!empty($numericIds)) {
                try {
                    $roleQuery = Role::whereIn('id', $numericIds);
                    if ($hasAssignmentHistory) {
                        $roleQuery->whereIn('slug', ['super_admin', 'superadmin', 'admin', 'project_admin', 'project-admin']);
                    }
                    $systemRoleNames = $roleQuery->pluck('name')->toArray();
                    $badges = array_merge($badges, $systemRoleNames);
                } catch (\Throwable) {
                }
            }
        }

        // Clean & Deduplicate (strictly roles only; no designations or duplicate aliases)
        $unique = [];
        $seen = [];
        foreach ($badges as $b) {
            $cleaned = trim((string) $b);
            if ($cleaned === '' || strcasecmp($cleaned, 'Member') === 0 || strcasecmp($cleaned, 'Team Member') === 0) {
                continue;
            }
            $norm = strtolower(preg_replace('/[^a-z0-9]/', '', $cleaned));
            if (!isset($seen[$norm])) {
                $seen[$norm] = true;
                $unique[] = $cleaned;
            }
        }

        // Deduplicate aliases
        $hasBadge = static fn (string $target): bool => collect($unique)
            ->contains(fn ($u) => strcasecmp((string) $u, $target) === 0);

        if ($hasBadge('Vehicle Driver')) {
            $unique = array_values(array_filter($unique, fn ($u) => strcasecmp($u, 'Driver') !== 0));
        }
        if ($hasBadge('Surveyor')) {
            $unique = array_values(array_filter($unique, fn ($u) => strcasecmp($u, 'Survey Man') !== 0));
        }
        if ($hasBadge('Draft Person')) {
            $unique = array_values(array_filter($unique, fn ($u) => strcasecmp($u, 'Draft Man') !== 0));
        }

        return $unique;
    }

    public function getRolesFormattedAttribute(): string
    {
        if (array_key_exists('roles_formatted', $this->attributes) && is_string($this->attributes['roles_formatted'])) {
            return $this->attributes['roles_formatted'];
        }

        $badges = $this->role_badges;
        return !empty($badges) ? '[' . implode('] [', $badges) . ']' : '';
    }

    public function appNotifications(string $model = 'member'): HasMany
    {
        return $this->hasMany(Notification::class, 'listing_id')
            ->where('model', $model);
    }

    public function unreadAppNotifications(string $model = 'member'): HasMany
    {
        return $this->appNotifications($model)
            ->where('status', 'unread')
            ->whereNull('viewed_at');
    }

    public function unreadAppNotificationsCount(string $model = 'member'): int
    {
        return $this->unreadAppNotifications($model)->count();
    }

    public function markAppNotificationAsRead(string $uuid, string $model = 'member'): bool
    {
        $notification = $this->appNotifications($model)->where('uuid', $uuid)->first();
        if (!$notification) {
            return false;
        }

        $notification->update([
            'status' => 'read',
            'viewed_at' => $notification->viewed_at ?? now(),
        ]);

        return true;
    }

    public function markAllAppNotificationsAsRead(string $model = 'member'): int
    {
        return $this->unreadAppNotifications($model)->update([
            'status' => 'read',
            'viewed_at' => now(),
        ]);
    }

    public function deleteAppNotification(string $uuid, string $model = 'member'): bool
    {
        $notification = $this->appNotifications($model)->where('uuid', $uuid)->first();
        if (!$notification) {
            return false;
        }

        $notification->delete();
        return true;
    }

    public function deleteAllAppNotifications(string $model = 'member'): int
    {
        return $this->appNotifications($model)->delete();
    }

    public function generatePasswordResetToken()
    {
        $this->reset_password_token = Str::random(60);
        $this->reset_password_token_expires_at = now()->addMinutes(10);
        $this->save();

        return $this->reset_password_token;
    }

    public function clearPasswordResetToken()
    {
        $this->reset_password_token = null;
        $this->reset_password_token_expires_at = null;
        $this->save();
    }

    public function getEmployeeCodeAttribute(): string
    {
        if ($this->relationLoaded('employee') && $this->employee && !empty($this->employee->employee_id)) {
            return 'Employee ID: ' . $this->employee->employee_id;
        }

        try {
            $emp = Employee::where('member_id', $this->id)->first();
            if ($emp && !empty($emp->employee_id)) {
                return 'Employee ID: ' . $emp->employee_id;
            }
        } catch (\Throwable) {
            // Defensive guard when database is not connected or in test mock mode
        }

        // Standardized fallback formatted as shown in screenshot (e.g. EMP00125)
        $cleanId = sprintf('%05d', abs(crc32($this->id)) % 100000);
        return 'Employee ID: EMP' . $cleanId;
    }
}
