<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    use HasUuids;
    protected $fillable = [
        'user_id',
        'from_id',
        'type',
        'source_type',
        'source_id',
        'title',
        'content',
        'firebase',
        'extra',
        'read_at'
    ];

    /**
     * Get the user that owns the notification.
     */

    protected $casts = [
        'firebase' => 'array',
        'extra' => 'array',
        'read_at' => 'datetime',
    ];

    public function uniqueIds()
    {
        return ['uuid'];
    }
}
