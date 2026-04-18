<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Job extends Model
{
    use HasFactory;

    protected $table = 'job_posts';

    protected $appends = [
        'responsibilities',
        'requirements',
    ];

    protected $fillable = [
        'uuid',
        'title',
        'company',
        'description',
        'location',
        'job_type',
        'experience',
        'salary',
        'skills',
        'perks',
        'key_responsibilities',
        'qualifications',
        'last_date',
        'company_image',
        'applicants',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'rejection_reason',
        'resubmitted_at',
        'approval_logs',
    ];

    protected $casts = [
        'skills' => 'array',
        'perks' => 'array',
        'approval_logs' => 'array',
        'approved_at' => 'datetime',
        'resubmitted_at' => 'datetime',
        'last_date' => 'date',
    ];

    public function getCompanyImageAttribute($value)
    {
        if (empty($value)) {
            return null;
        }

        if (filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        if (str_starts_with($value, '/storage/')) {
            return $value;
        }

        if (str_starts_with($value, 'storage/')) {
            return '/' . $value;
        }

        return Storage::disk('public')->url($value);
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($job) {
            if (empty($job->uuid)) {
                $job->uuid = (string) Str::uuid();
            }
        });
    }

    /**
     * Get key_responsibilities as responsibilities array
     */
    public function getResponsibilitiesAttribute()
    {
        $value = $this->key_responsibilities;
        if (empty($value)) {
            return [];
        }
        // Split by newlines and filter empty items, re-index array
        return array_values(array_filter(array_map('trim', explode("\n", $value))));
    }

    /**
     * Get qualifications as requirements array
     */
    public function getRequirementsAttribute()
    {
        $value = $this->qualifications;
        if (empty($value)) {
            return [];
        }
        // Split by newlines and filter empty items, re-index array
        return array_values(array_filter(array_map('trim', explode("\n", $value))));
    }

    /**
     * Get the member who created this job
     */
    public function creator()
    {
        return $this->belongsTo(Member::class, 'created_by');
    }

    /**
     * Get the super admin who approved this job
     */
    public function approver()
    {
        return $this->belongsTo(SuperAdmin::class, 'approved_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(JobApplication::class, 'job_id');
    }

    /**
     * Scope for pending jobs
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope for active jobs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for declined jobs
     */
    public function scopeDeclined($query)
    {
        return $query->where('status', 'declined');
    }

    /**
     * Approve the job
     */
    public function approve($superAdminId)
    {
        $this->update([
            'status' => 'active',
            'approved_by' => $superAdminId,
            'approved_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->addApprovalLog('approved', $superAdminId);
    }

    /**
     * Reject the job
     */
    public function reject($superAdminId, $reason = null)
    {
        $this->update([
            'status' => 'declined',
            'approved_by' => $superAdminId,
            'rejection_reason' => $reason,
        ]);

        $this->addApprovalLog('rejected', $superAdminId, $reason);
    }

    /**
     * Resend for approval
     */
    public function resend()
    {
        $this->update([
            'status' => 'pending',
            'resubmitted_at' => now(),
            'rejection_reason' => null,
        ]);

        $this->addApprovalLog('resubmitted', $this->created_by);
    }

    /**
     * Add approval log entry
     */
    private function addApprovalLog($action, $userId, $reason = null)
    {
        $logs = $this->approval_logs ?? [];
        $logs[] = [
            'action' => $action,
            'user_id' => $userId,
            'reason' => $reason,
            'timestamp' => now()->toDateTimeString(),
        ];

        $this->update(['approval_logs' => $logs]);
    }
}
