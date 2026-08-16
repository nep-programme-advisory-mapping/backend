<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAdviserSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Enforced by the route's permission:advisory.update middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'assign_to_staff_user_id' => ['nullable', 'integer', 'exists:staff_users,id'],
            'coordinator_id'           => ['nullable', 'integer', 'exists:users,id'],
            'section_profile'             => ['nullable', 'string', 'max:10000'],
            'section_gaps'                 => ['nullable', 'string', 'max:10000'],
            'section_coordinators_notes'   => ['nullable', 'string', 'max:10000'],
            'final_note_file'              => ['nullable', 'string', 'max:255'],
            'file'                         => ['nullable', 'file', 'mimes:pdf,doc,docx,xls,xlsx,png,jpg,jpeg', 'max:10240'],
            'recommendations'              => ['nullable', 'array'],
            'recommendations.*.organisation_name'  => ['nullable', 'string', 'max:255'],
            'recommendations.*.programme_name'     => ['nullable', 'string', 'max:255'],
            'recommendations.*.type'               => ['required_with:recommendations', 'string', 'max:100'],
            'recommendations.*.relational'         => ['nullable', 'string', 'max:10000'],
            'recommendations.*.rationale'          => ['nullable', 'string', 'max:10000'],
            'recommendations.*.programme_entry_id' => ['nullable', 'integer', 'exists:programme_entries,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'assign_to_staff_user_id.exists' => 'The selected staff user does not exist.',
        ];
    }
}