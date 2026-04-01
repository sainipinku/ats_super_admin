<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Job extends Model
{
    use HasFactory;

    protected $table = 'job_posts';

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
