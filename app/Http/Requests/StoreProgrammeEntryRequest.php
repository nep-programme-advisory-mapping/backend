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

        // Autosave/draft creates go through this same endpoint with no
        // programme_name/start_year yet — a brand new entry must be
        // creatable empty. Only an explicit publish attempt (is_submitted
        // sent truthy) requires the full Section 1 field set; see
        // UpdateProgrammeEntryRequest for the matching rule plus the
        // cross-resource (activities/geography/keywords) publish check.
        $isPublishing = $this->boolean('is_submitted');
        $requiredWhenPublishing = $isPublishing ? 'required' : 'nullable';

        return [
            'organisation_id' => [
                $isNepStaff ? 'required' : 'prohibited',
                'exists:organisations,id',
            ],
            'budget_band_id' => [$requiredWhenPublishing, 'exists:budget_bands,id'],
            'programme_name' => [$requiredWhenPublishing, 'string', 'max:255'],
            'start_year' => [$requiredWhenPublishing, 'integer', 'min:1900', 'max:2100'],
            'end_year' => ['nullable', 'integer', 'min:1900', 'max:2100', 'gte:start_year'],
            'ongoing' => ['sometimes', 'boolean'],
            'fte_staff' => [$isPublishing ? 'required' : 'sometimes', 'numeric', 'min:0'],
            'indirect_beneficiaries' => [$isPublishing ? 'required' : 'sometimes', 'integer', 'min:0'],
            'direct_beneficiaries' => [$isPublishing ? 'required' : 'sometimes', 'integer', 'min:0'],
            'method' => ['nullable', 'string'],
            'verified_date' => ['nullable', 'date'],
            'is_submitted' => ['sometimes', 'boolean'],
        ];
    }

    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) {
            if ($this->boolean('is_submitted') && !$this->boolean('ongoing') && !$this->filled('end_year')) {
                $validator->errors()->add('end_year', 'The end year field is required unless the programme is ongoing.');
            }
        });
    }

    public function messages(): array
    {
        return [
            'organisation_id.required' => 'organisation_id is required when creating an entry as NEP Admin or NEP Coordinator.',
            'organisation_id.prohibited' => 'organisation_id cannot be set manually — entries are automatically assigned to your own organisation.',
        ];
    }
}