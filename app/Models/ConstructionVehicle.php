<?php

namespace App\Models;

use App\Models\Project;
use App\Models\VehicleAssignment;
use App\Models\VehicleLocationPing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ConstructionVehicle extends Model
{
    use SoftDeletes;

    protected $table = 'construction_vehicles';

    protected $fillable = [
        'project_id',
        'vehicle_code',
        'registration_number',
        'vehicle_type',
        'make',
        'model',
        'status',
        'created_by_type',
        'created_by_id',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function createdBy(): MorphTo
    {
        return $this->morphTo();
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class, 'vehicle_id');
    }

    public function locationPings(): HasMany
    {
        return $this->hasMany(VehicleLocationPing::class, 'vehicle_id');
    }
}

