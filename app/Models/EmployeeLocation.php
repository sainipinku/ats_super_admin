<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

class EmployeeLocation extends Model
{
    protected $fillable = [
        'member_id',
        'latitude',
        'longitude',
        'accuracy',
        'captured_at',
    ];

    protected $casts = [
        'latitude' => 'float',
        'longitude' => 'float',
        'accuracy' => 'float',
        'captured_at' => 'datetime',
    ];

    public static function recordIfValid(
        Member $member,
        mixed $latitude,
        mixed $longitude,
        mixed $accuracy = null,
        mixed $capturedAt = null
    ): ?self {
        $employee = $member->employee;
        if (!$employee && class_exists(Employee::class)) {
            $employee = Employee::firstOrCreate(
                ['member_id' => $member->getKey()],
                ['uuid' => (string) \Illuminate\Support\Str::uuid()]
            );
            $member->setRelation('employee', $employee);
        }

        if (
            (int) $member->status !== Member::STATUS_ACTIVE
            || !$employee
            || !is_numeric($latitude)
            || !is_numeric($longitude)
            || $latitude < -90
            || $latitude > 90
            || $longitude < -180
            || $longitude > 180
        ) {
            return null;
        }

        $validAccuracy = is_numeric($accuracy) && $accuracy >= 0 && $accuracy <= 100000
            ? $accuracy
            : null;

        $capturedAt = $capturedAt ? Carbon::parse($capturedAt) : now();
        if (
            Schema::hasColumn('members', 'latitude')
            && Schema::hasColumn('members', 'longitude')
            && Schema::hasColumn('members', 'location_captured_at')
            && (!$member->location_captured_at || $capturedAt->greaterThanOrEqualTo($member->location_captured_at))
        ) {
            $member->forceFill([
                'latitude' => $latitude,
                'longitude' => $longitude,
                'location_captured_at' => $capturedAt,
            ])->save();
        }

        if (!Schema::hasTable('employee_locations')) {
            return null;
        }

        return static::create([
            'member_id' => $member->getKey(),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'accuracy' => $validAccuracy,
            'captured_at' => $capturedAt,
        ]);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
