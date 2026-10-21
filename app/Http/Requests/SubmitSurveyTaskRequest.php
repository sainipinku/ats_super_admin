<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class SubmitSurveyTaskRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $maxFileSizeKb = (int) config('survey.upload_max_kb', 10240);

        return [
            'day_number' => ['nullable', 'integer', 'min:1'],
            'checklist_ids' => ['sometimes', 'array'],
            'checklist_ids.*' => ['integer', 'distinct'],
            'work_status' => ['required', 'in:completed,partial,not_completed'],
            'has_obstruction' => ['required', 'boolean'],
            'obstruction_details' => ['required_if:has_obstruction,1,true,yes,on', 'nullable', 'string'],
            'pending_work' => ['required_if:work_status,partial,not_completed', 'nullable', 'string'],
            'start_time' => ['required', 'string', 'max:50'],
            'end_time' => ['required', 'string', 'max:50'],
            'total_hours' => ['required', 'string', 'max:50'],
            'remarks' => ['nullable', 'string'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'gps_verified' => ['nullable', 'boolean'],
            'photos' => ['sometimes', 'array'],
            'photos.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', "max:{$maxFileSizeKb}"],
            'survey_data_files' => ['sometimes', 'array'],
            'survey_data_files.*' => ['file', 'extensions:csv,xls,xlsx,raw,kml,kmz,pdf', "max:{$maxFileSizeKb}"],
            'sketch_markup_files' => ['sometimes', 'array'],
            'sketch_markup_files.*' => ['file', 'mimes:jpg,jpeg,png,pdf', "max:{$maxFileSizeKb}"],
            'reference_document_files' => ['sometimes', 'array'],
            'reference_document_files.*' => ['file', 'extensions:pdf,dwg,dxf,xls,xlsx,doc,docx,jpg,jpeg,png,webp', "max:{$maxFileSizeKb}"],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'The submitted data is invalid.',
            'errors' => $validator->errors(),
        ], 422));
    }
}
