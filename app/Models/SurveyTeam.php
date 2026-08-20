<?php

namespace App\Models\Construction;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SurveyTeam extends Model
{
    use SoftDeletes;

    protected $table = 'construction_survey_teams';

    protected $fillable = [
        'project_id',
        'team_number',
        'team_name',
        'supervisor_member_id',
        'created_by_type',
        'created_by_id',
    ];

    protected $casts = [
        'team_number' => 'integer',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'supervisor_member_id');
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function surveyPlanMembers(): HasMany
    {
        return $this->hasMany(SurveyPlanMember::class, 'survey_team_id');
    }
}
