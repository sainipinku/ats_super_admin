<?php

namespace App\Services\Construction;

use App\Models\Construction\Document;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ConstructionDocumentService
{
    public function createPlaceholderDocument(
        Model $documentable,
        ?Model $actor,
        string $folder,
        string $originalName,
        ?int $companyId = null,
        ?int $projectId = null,
        ?string $disk = null,
        ?string $mimeType = null
    ): Document {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $sanitizedBaseName = Str::slug($baseName) ?: Str::uuid()->toString();
        $fileName = $extension !== ''
            ? "{$sanitizedBaseName}.{$extension}"
            : $sanitizedBaseName;

        return Document::create([
            'company_id' => $companyId,
            'project_id' => $projectId,
            'documentable_type' => $documentable::class,
            'documentable_id' => $documentable->getKey(),
            'folder' => trim($folder, '/'),
            'file_name' => $fileName,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'disk' => $disk ?: config('filesystems.default', 'local'),
            'path' => trim($folder, '/') . '/' . $fileName,
            'uploaded_by_type' => $actor ? $actor::class : null,
            'uploaded_by_id' => $actor?->getKey(),
        ]);
    }
}
