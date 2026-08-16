<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProgrammeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user();
        // A user bound to an organisation (member_org, or any custom role
        // assigned one) always creates for that org — organisation_id is
        // auto-assigned and must not be sent. A user with organisation-wide
        // access (staff, or a super admin regardless of their own org) must
        // specify which org they're creating on behalf of.
        $isNepStaff = $user->hasOrganisationWideAccess();

        return [
            'organisation_id' => [
                $isNepStaff ? 'required' : 'prohibited',
                'exists:organisations,id',
            ],
            'budget_band_id' => ['nullable', 'exists:budget_bands,id'],
            'programme_name' => ['required', 'string', 'max:255'],
            'start_year' => ['required', 'integer', 'min:1900', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:start_year'],
            'ongoing' => ['sometimes', 'boolean'],
            'fte_staff' => ['sometimes', 'numeric', 'min:0'],
            'indirect_beneficiaries' => ['sometimes', 'integer', 'min:0'],
            'direct_beneficiaries' => ['sometimes', 'integer', 'min:0'],
            'method' => ['nullable', 'string'],
            'verified_date' => ['nullable', 'date'],
            'is_submitted' => ['sometimes', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'organisation_id.required' => 'organisation_id is required when creating an entry as NEP Admin or NEP Coordinator.',
            'organisation_id.prohibited' => 'organisation_id cannot be set manually — entries are automatically assigned to your own organisation.',
        ];
    }
}