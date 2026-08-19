<?php

namespace App\Models;

use App\Models\ConstructionEquipment;

use App\Models\Member;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EquipmentUsageLog extends Model
{
    protected $table = 'construction_equipment_usage_logs';

    protected $fillable = [
        'project_id',
        'equipment_id',
        'member_id',
        'log_date',
        'hours_used',
        'latitude',
        'longitude',
        'gps_accuracy_meters',
        'gps_verified',
        'notes',
    ];

    protected $casts = [
        'log_date' => 'date',
        'hours_used' => 'float',
        'latitude' => 'float',
        'longitude' => 'float',
        'gps_accuracy_meters' => 'float',
        'gps_verified' => 'boolean',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function equipment(): BelongsTo
    {
        return $this->belongsTo(ConstructionEquipment::class, 'equipment_id');
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'member_id');
    }
}

