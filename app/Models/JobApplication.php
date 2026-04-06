<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobApplication extends Model
{
    use HasFactory;

    protected $fillable = [
        'uuid',
        'job_id',
        'candidate_id',
        'cover_letter',
        'resume_url',
        'answers',
        'status',
        'admin_notes',
        'reviewed_at',
        'reviewed_by',
        'candidate_name',
        'candidate_email',
        'candidate_phone',
        'candidate_skills',
        'candidate_experience',
    ];

    protected $casts = [
        'answers' => 'json',
        'candidate_skills' => 'json',
        'candidate_experience' => 'json',
        'reviewed_at' => 'datetime',
    ];

    /**
     * Get the job associated with this application.
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class, 'job_id');
    }

    /**
     * Get the candidate (member) who applied.
     */
    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'candidate_id');
    }

    /**
     * Get the admin who reviewed this application.
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by');
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($application) {
            if (empty($application->uuid)) {
                $application->uuid = (string) \Illuminate\Support\Str::uuid();
            }
        });
    }
}
