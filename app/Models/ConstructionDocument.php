<?php

namespace App\Models;

use App\Models\Company;
use App\Models\Project;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ConstructionDocument extends Model
{
    protected $table = 'construction_documents';

    protected $fillable = [
        'company_id',
        'project_id',
        'documentable_type',
        'documentable_id',
        'folder',
        'file_name',
        'original_name',
        'mime_type',
        'file_size',
        'disk',
        'path',
        'version_no',
        'uploaded_by_type',
        'uploaded_by_id',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function documentable(): MorphTo
    {
        return $this->morphTo();
    }

    public function uploadedBy(): MorphTo
    {
        return $this->morphTo();
    }
}
