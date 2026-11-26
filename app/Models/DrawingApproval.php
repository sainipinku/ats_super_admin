<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DrawingApproval extends Model
{
    protected $table = 'construction_drawing_approvals';

    protected $fillable = [
        'project_id',
        'drawing_revision_id',
        'requested_by_type',
        'requested_by_id',
        'requested_at',
        'assigned_to_type',
        'assigned_to_id',
        'assigned_by_type',
        'assigned_by_id',
        'assigned_at',
        'approved_by_type',
        'approved_by_id',
        'approved_at',
        'decision',
        'remarks',
        'skip_junior_review',
        'review_level',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'assigned_at' => 'datetime',
        'approved_at' => 'datetime',
        'skip_junior_review' => 'boolean',
    ];

    public function assignedTo(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'assigned_to_type', 'assigned_to_id');
    }

    public function assignedBy(): \Illuminate\Database\Eloquent\Relations\MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'assigned_by_type', 'assigned_by_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function drawingRevision(): BelongsTo
    {
        return $this->belongsTo(DrawingRevision::class);
    }

    public function requestedBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function approvedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
