<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class EquipmentCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'equipment_categories';

    protected $fillable = [
        'category_id',
        'category_name',
        'description',
        'status',
    ];

    public static function boot(): void
    {
        parent::boot();
        static::creating(function ($category) {
            if (empty($category->category_id)) {
                $category->category_id = static::generateCategoryId();
            }
        });
    }

    public static function generateCategoryId(): string
    {
        $prefix = 'EC-';
        do {
            $random = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $categoryId = $prefix . $random;
        } while (static::where('category_id', $categoryId)->exists());

        return $categoryId;
    }

    public function equipments()
    {
        return $this->hasMany(Equipment::class, 'category_id');
    }
}