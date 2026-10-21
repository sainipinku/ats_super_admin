<?php

namespace App\Models;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Support\Construction\SurveyStatus;

class SurveyVisit extends Model
{



public const STATUS_IN_PROGRESS = SurveyStatus::IN_PROGRESS;

public const STATUS_SUBMITTED = SurveyStatus::SUBMITTED;

public const STATUS_APPROVED = SurveyStatus::APPROVED;

public const STATUS_REJECTED = SurveyStatus::REJECTED;

public const STATUS_REVISION_REQUESTED = SurveyStatus::REVISION_REQUESTED;

    protected $table = 'construction_survey_visits';

    protected $fillable = [
        'project_id',
        'survey_plan_id',
        'task_id',
        'execution_task_id',
        'checked_in_by_member_id',
        'check_in_at',
        'check_in_latitude',
        'check_in_longitude',
        'check_out_at',
        'check_out_latitude',
        'check_out_longitude',
        'duration_minutes',
        'day_number',
        'elevation_m',
        'distance_covered_m',
        'total_points_captured',
        'remarks',
        'photos',
        'file_path',
        'notes',
        'gps_distance_meters',
        'gps_verified',
        'status',
    ];

      protected $appends = [
    'status_key',
    'status_label',
];

    protected $casts = [
        'check_in_at' => 'datetime',
        'check_out_at' => 'datetime',
        'check_in_latitude' => 'float',
        'check_in_longitude' => 'float',
        'check_out_latitude' => 'float',
        'check_out_longitude' => 'float',
        'elevation_m' => 'float',
        'distance_covered_m' => 'float',
        'total_points_captured' => 'integer',
        'duration_minutes' => 'integer',
        'day_number' => 'integer',
        'photos' => 'array',
        'gps_distance_meters' => 'float',
        'gps_verified' => 'boolean',
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

    public static function toFullUrl(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        $clean = ltrim($path, '/');

        if (str_starts_with($clean, 'storage/')) {
            return asset($clean);
        }

        return asset('storage/' . $clean);
    }

    public function getFilePathAttribute($value): ?string
    {
        return self::toFullUrl($value);
    }

    public function getPhotosAttribute($value): array
    {
        $list = is_string($value)
            ? (json_decode($value, true) ?: [])
            : (is_array($value) ? $value : []);

        return array_values(array_filter(array_map(function ($photo) {
            return self::toFullUrl(is_string($photo) ? $photo : ($photo['url'] ?? $photo['path'] ?? null));
        }, (array) $list)));
    }

    public function setPhotosAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['photos'] = json_encode(array_values($value), JSON_UNESCAPED_SLASHES);
        } else {
            $this->attributes['photos'] = $value;
        }
    }

    public function getNotesAttribute($value)
    {
        if (is_array($value)) {
            return $this->formatNotesUrls($value);
        }

        if (is_string($value) && $value !== '') {
            $trimmed = trim($value);
            if ((str_starts_with($trimmed, '{') && str_ends_with($trimmed, '}'))
                || (str_starts_with($trimmed, '[') && str_ends_with($trimmed, ']'))) {
                $decoded = json_decode($trimmed, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    return $this->formatNotesUrls($decoded);
                }
            }
        }

        return $value;
    }

    public function setNotesAttribute($value): void
    {
        if (is_array($value)) {
            $this->attributes['notes'] = json_encode($value, JSON_UNESCAPED_SLASHES);
        } else {
            $this->attributes['notes'] = $value;
        }
    }

    public function formatNotesUrls(array $notes): array
    {
        if (!empty($notes['categorized_files']) && is_array($notes['categorized_files'])) {
            foreach ($notes['categorized_files'] as $category => $files) {
                if (is_array($files)) {
                    foreach ($files as $idx => $f) {
                        if (is_array($f)) {
                            if (!empty($f['url'])) {
                                $notes['categorized_files'][$category][$idx]['url'] = self::toFullUrl($f['url']);
                            }
                            if (!empty($f['path'])) {
                                $notes['categorized_files'][$category][$idx]['full_url'] = self::toFullUrl($f['path']);
                            }
                        }
                    }
                }
            }
        }

        if (!empty($notes['member_reply']['files']) && is_array($notes['member_reply']['files'])) {
            foreach ($notes['member_reply']['files'] as $idx => $f) {
                if (is_array($f) && !empty($f['url'])) {
                    $notes['member_reply']['files'][$idx]['url'] = self::toFullUrl($f['url']);
                }
            }
        }

        if (!empty($notes['revision_history']) && is_array($notes['revision_history'])) {
            foreach ($notes['revision_history'] as $idx => $turn) {
                if (!empty($turn['files']) && is_array($turn['files'])) {
                    foreach ($turn['files'] as $fIdx => $f) {
                        if (is_array($f) && !empty($f['url'])) {
                            $notes['revision_history'][$idx]['files'][$fIdx]['url'] = self::toFullUrl($f['url']);
                        }
                    }
                }
            }
        }

        if (!empty($notes['file_path'])) {
            $notes['file_path'] = self::toFullUrl($notes['file_path']);
        }
        if (!empty($notes['primary_file_path'])) {
            $notes['primary_file_path'] = self::toFullUrl($notes['primary_file_path']);
        }

        return $notes;
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function surveyPlan(): BelongsTo
    {
        return $this->belongsTo(SurveyPlan::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class, 'task_id');
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'checked_in_by_member_id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(SurveyEntry::class);
    }

    public function measurements(): HasMany
    {
        return $this->hasMany(SurveyMeasurement::class);
    }

    public function submission(): HasOne
    {
        return $this->hasOne(SurveySubmission::class);
    }
}
