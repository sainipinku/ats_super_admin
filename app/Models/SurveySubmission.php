<?php

namespace App\Models;

use App\Models\Member;
use App\Models\TaskChecklist;
use App\Models\DraftingJob;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\Construction\SurveyStatus;

class SurveySubmission extends Model
{



public const STATUS_DRAFT = SurveyStatus::DRAFT;

public const STATUS_SUBMITTED = SurveyStatus::SUBMITTED;

public const STATUS_APPROVED = SurveyStatus::APPROVED;

public const STATUS_REVISION_REQUESTED = SurveyStatus::REVISION_REQUESTED;

public const STATUS_REJECTED = SurveyStatus::REJECTED;




    protected $table = 'construction_survey_submissions';

    protected $fillable = [
        'project_id',
        'survey_visit_id',
        'submitted_by_member_id',
        'submitted_at',
        'status',
        'review_notes',
        'reviewed_by_member_id',
        'reviewed_at',
    ];

    protected $appends = [
        'status_key',
        'status_label',
        'submission_details',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'status' => 'integer',
    ];

    public function getStatusKeyAttribute(): string
    {
        return SurveyStatus::key((int) $this->status);
    }

    public function getStatusLabelAttribute(): string
    {
        return SurveyStatus::label((int) $this->status);
    }

    public function getSubmissionDetailsAttribute(): array
    {
        $visit = $this->surveyVisit;
        $meta = [];
        if ($visit && !empty($visit->notes)) {
            if (is_array($visit->notes)) {
                $meta = $visit->notes;
            } elseif (is_string($visit->notes)) {
                $decoded = json_decode($visit->notes, true);
                if (is_array($decoded)) {
                    $meta = $decoded;
                }
            }
        }

        $taskId = $visit?->task_id;
        $checklists = [];
        if ($taskId) {
            try {
                $checklists = TaskChecklist::where('execution_task_id', $taskId)
                    ->get(['id', 'item_title', 'is_completed', 'notes', 'status'])
                    ->map(fn ($c) => [
                        'id' => $c->id,
                        'item_title' => $c->item_title,
                        'is_completed' => (bool) $c->is_completed,
                        'notes' => $c->notes,
                        'status' => $c->status,
                    ])
                    ->toArray();
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $completedCount = count(array_filter($checklists, fn ($c) => !empty($c['is_completed'])));
        $totalCount = count($checklists);

        $photos = is_array($visit?->photos) ? $visit->photos : [];

        $categorizedFiles = $meta['categorized_files'] ?? [
            'survey_data' => $visit?->file_path ? [
                [
                    'name' => basename($visit->file_path),
                    'url' => $visit->file_path,
                    'size' => '',
                    'extension' => pathinfo($visit->file_path, PATHINFO_EXTENSION),
                ]
            ] : [],
            'sketch_markup' => [],
            'reference_documents' => [],
        ];

        return [
            'task_id' => $taskId,
            'work_status' => $meta['work_status'] ?? ($this->status === SurveyStatus::APPROVED ? 'completed' : 'partial'),
            'has_obstruction' => (bool) ($meta['has_obstruction'] ?? false),
            'obstruction_details' => $meta['obstruction_details'] ?? null,
            'pending_work' => $meta['pending_work'] ?? null,
            'start_time' => $meta['start_time'] ?? null,
            'end_time' => $meta['end_time'] ?? null,
            'total_hours' => $meta['total_hours'] ?? ($visit?->duration_minutes ? round($visit->duration_minutes / 60, 1) . 'h' : null),
            'photos' => $photos,
            'categorized_files' => $categorizedFiles,
            'checklists' => $checklists,
            'checklist_completed_count' => $completedCount,
            'checklist_total_count' => $totalCount,
            'remarks' => $visit?->remarks ?? ($meta['remarks'] ?? null),
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyVisit(): BelongsTo
    {
        return $this->belongsTo(SurveyVisit::class);
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'submitted_by_member_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'reviewed_by_member_id');
    }

    public function draftingJobs(): HasMany
    {
        return $this->hasMany(DraftingJob::class);
    }
}
