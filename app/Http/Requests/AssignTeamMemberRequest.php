<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignTeamMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Authorization handled by middleware
    }

    public function rules(): array
    {
        return [
            'member_id' => [
                'required',
                'integer',
                'exists:members,id',
            ],
            'role_id' => [
                'nullable',
                'integer',
                'exists:construction_roles,id',
            ],
            'assigned_from' => [
                'nullable',
                'date',
            ],
            'assigned_to' => [
                'nullable',
                'date',
                'after_or_equal:assigned_from',
            ],
            'assignment_scope' => [
                'nullable',
                'string',
                'max:500',
            ],
            'is_primary' => [
                'boolean',
            ],
            'status' => [
                'required',
                Rule::in(['active', 'inactive']),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'member_id.required' => 'Please select a team member.',
            'member_id.exists' => 'The selected member does not exist.',
            'role_id.exists' => 'The selected role does not exist.',
            'assigned_to.after_or_equal' => 'The assignment end date must be after or equal to the start date.',
            'status.in' => 'The status must be either active or inactive.',
        ];
    }
}