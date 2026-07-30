<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreEquipmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $equipmentId = $this->route('id');
        $equipment = null;

        if ($equipmentId) {
            $equipment = \App\Models\Equipment::find($equipmentId);
        }

        return [
            'category_id' => ['required', 'exists:equipment_categories,id'],
            'equipment_name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'serial_number' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('equipments', 'serial_number')->ignore($equipment?->id)->whereNull('deleted_at'),
            ],
            'asset_tag' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('equipments', 'asset_tag')->ignore($equipment?->id)->whereNull('deleted_at'),
            ],
            'purchase_date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'warranty_till' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:purchase_date'],
            'photo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
            'status' => ['required', 'integer', 'in:0,1,2,3,4,5'],
            'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'assigned_project_id' => ['nullable', 'exists:construction_projects,id'],
            'assigned_date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function messages(): array
    {
        return [
            'category_id.required' => 'Please select a category.',
            'category_id.exists' => 'Please select a valid category.',
            'equipment_name.required' => 'Equipment name is required.',
            'serial_number.unique' => 'This serial number is already registered.',
            'status.required' => 'Please select a status.',
            'status.in' => 'Please select a valid status.',
            'photo.image' => 'File must be an image.',
            'photo.max' => 'Image must not exceed 5MB.',
            'purchase_date.date_format' => 'Please enter a valid purchase date (YYYY-MM-DD).',
            'warranty_till.after_or_equal' => 'Warranty till date must be after purchase date.',
            'assigned_employee_id.exists' => 'Please select a valid employee.',
            'assigned_project_id.exists' => 'Please select a valid project.',
        ];
    }
}