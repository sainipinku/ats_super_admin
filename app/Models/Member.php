<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'name',
        'username',
        'email',
        'phone',
        'password',
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
        'remember_token',
        'reset_password_token',
        'reset_password_token_expires_at',
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
    ];
    public function uniqueIds()
    {
        return ['uuid'];
    }


    public function creator()
    {
        return $this->belongsTo(Member::class, 'created_by');
    }


    protected $appends = [
        'profile_photo_url',
        'resume_url',
        'current_age',
        'department_names',
        'designation_names',
        'role_names',
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
            $q->whereJsonContains('roles', (string) $roleId);
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

    public function profilePhotoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if (!empty($this->image)) {
                    if (filter_var($this->image, FILTER_VALIDATE_URL)) {
                        return $this->image;
                    }

                    return Storage::disk('public')->url($this->image);
                }

                return asset('images/profileimg.png');
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

                if (Storage::disk('public')->exists($this->resume_path)) {
                    return Storage::disk('public')->url($this->resume_path);
                }

                return null;
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
        return $this->id === 1 || $this->slug === 'admin';
    }
 public function isSuperAdmin(): bool
    {
        return $this->id === 2 || $this->slug === 'super-admin';
    }
    /**
     * Check if the role is a member role
     */
    public function isMember(): bool
    {
        return $this->id === 3 || $this->slug === 'member';
    }


    public function getRoleName($roleId)
    {
        // Get role name from relationship
        return $this->roles()->where('id', $roleId)->value('name');
    }

    public function getGuardName(): string
    {
        return match ($this->id) {
            1 => 'admin',
            3 => 'member',
            default => 'web',
        };
    }

    /**
     * Get the dashboard route for this role
     */
    public function getDashboardRoute(): string
    {
        return match ($this->id) {
            1 => 'admin.dashboard',
            3 => 'member.dashboard',
            default => 'home',
        };
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
            return Department::whereIn('id', $this->departments)->pluck('name')->implode(', ');
        }
        return '';
    }
    public function getRoleNamesAttribute()
    {
        if (is_array($this->roles)) {
            return Role::whereIn('id', $this->roles)->pluck('name')->implode(', ');
        }
        return '';
    }

    public function getDesignationNamesAttribute()
    {
        if (is_array($this->designation)) {
            return Designation::whereIn('id', $this->designation)->pluck('name')->implode(', ');
        }
        return '';
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

    public function isPasswordResetTokenValid()
    {
        return $this->reset_password_token &&
            $this->reset_password_token_expires_at &&
            $this->reset_password_token_expires_at->isFuture();
    }

    
}
