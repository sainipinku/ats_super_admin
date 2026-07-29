<?php

namespace App\Models;

use App\Models\Construction\Project;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\Storage;

class Equipment extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipments';

    protected $fillable = [
        'equipment_id',
        'category_id',
        'equipment_name',
        'company',
        'brand',
        'model',
        'serial_number',
        'asset_tag',
        'purchase_date',
        'purchase_cost',
        'vendor',
        'warranty_till',
        'photo',
        'status',
        'assigned_employee_id',
        'assigned_project_id',
        'assigned_date',
    ];

    protected $appends = [
        'photo_url',
    ];

    protected $casts = [
        'purchase_date' => 'date',
        'warranty_till' => 'date',
        'assigned_date' => 'date',
        'purchase_cost' => 'decimal:2',
    ];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($equipment) {
            if (empty($equipment->equipment_id)) {
                $equipment->equipment_id = static::generateEquipmentId();
            }
        });
    }

    public static function generateEquipmentId(): string
    {
        $prefix = 'EQ-';
        do {
            $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $equipmentId = $prefix . $random;
        } while (static::where('equipment_id', $equipmentId)->exists());

        return $equipmentId;
    }

    public function category()
    {
        return $this->belongsTo(EquipmentCategory::class, 'category_id');
    }

    public function assignedEmployee()
    {
        return $this->belongsTo(Employee::class, 'assigned_employee_id');
    }

    public function assignedProject()
    {
        return $this->belongsTo(Project::class, 'assigned_project_id');
    }

    public function photoUrl(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->photo && Storage::disk('public')->exists($this->photo)) {
                    return Storage::disk('public')->url($this->photo);
                }
                return asset('images/common/data_not_found.png');
            }
        );
    }

    public function scopeNewestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(equipment_id, 4) AS UNSIGNED) DESC");
    }

    public function scopeOldestFirst($query)
    {
        return $query->orderByRaw("CAST(SUBSTRING(equipment_id, 4) AS UNSIGNED) ASC");
    }
}