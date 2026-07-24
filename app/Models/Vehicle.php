<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Vehicle extends Model
{
    use HasFactory, SoftDeletes, HasUuids;

    protected $fillable = [
        'uuid',
        'vehicle_id',
        'vehicle_type',
        'vehicle_number',
        'vehicle_name',
        'brand',
        'fuel_type',
        'color',
        'manufacturing_year',
        'engine_number',
        'chassis_number',
        'purchase_date',
        'purchase_amount',
        'current_km_reading',
        'vehicle_image',
        'status',

        'insurance_provider',
        'policy_number',
        'insurance_type',
        'insurance_start_date',
        'insurance_end_date',
        'insurance_status',

        'puc_certificate_number',
        'puc_issue_date',
        'puc_expiry_date',
        'puc_status',

        'challan_number',
        'challan_date',
        'violation_type',
        'fine_amount',
        'payment_status',
    ];

    protected $appends = [
        'vehicle_image_url',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($v) {
            if (empty($v->vehicle_id)) {
                $v->vehicle_id = static::generateVehicleId();
            }
            if ($v->insurance_end_date) {
                $v->insurance_status = now()->lessThanOrEqualTo($v->insurance_end_date) ? 'Active' : 'Expired';
            }
            if ($v->puc_expiry_date) {
                $v->puc_status = now()->lessThanOrEqualTo($v->puc_expiry_date) ? 'Valid' : 'Expired';
            }
        });
        static::updating(function ($v) {
            if ($v->insurance_end_date) {
                $v->insurance_status = now()->lessThanOrEqualTo($v->insurance_end_date) ? 'Active' : 'Expired';
            }
            if ($v->puc_expiry_date) {
                $v->puc_status = now()->lessThanOrEqualTo($v->puc_expiry_date) ? 'Valid' : 'Expired';
            }
        });
    }

    public static function generateVehicleId(): string
    {
        $prefix = 'VHC-';
        $lastVehicle = static::withTrashed()
            ->where('vehicle_id', 'like', $prefix . '%')
            ->orderByRaw('CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) DESC')
            ->first();

        if ($lastVehicle) {
            $lastNumber = (int) substr($lastVehicle->vehicle_id, 4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 6, '0', STR_PAD_LEFT);
    }

    public function vehicleImageUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->vehicle_image && Storage::disk('public')->exists($this->vehicle_image)) {
                    return Storage::disk('public')->url($this->vehicle_image);
                }
                return asset('images/common/data_not_found.png');
            }
        );
    }

    public function scopeNewestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) DESC");
    }

    public function scopeOldestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(vehicle_id, 5) AS UNSIGNED) ASC");
    }

    public function scopeSortByVehicleNumber($query)
    {
        return $query->orderBy('vehicle_number', 'asc');
    }
}