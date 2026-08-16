<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGovernmentAgreementsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization handled in controller via canManage()
    }

    public function rules(): array
    {
        $programmeEntry = $this->route('programmeEntry');

        // Government agreements aren't required to publish at all (see
        // hasCompletedAllRequired in programmeForm.ts — section 4 is
        // optional), but a partially-typed row still shouldn't 422 during
        // autosave. Once the entry is published, treat any row the user did
        // add as needing its fields filled in properly.
        $isSubmitted = $programmeEntry?->is_submitted;
        $requiredIfSubmitted = $isSubmitted ? 'required' : 'sometimes';

        return [
            'agreements' => ['present', 'array'],
            'agreements.*.id' => [
                'nullable',
                'integer',
                Rule::exists('government_agreements', 'id')
                    ->where('programme_entry_id', $programmeEntry->id),
            ],
            'agreements.*.counterpart_agency' => [
                $requiredIfSubmitted,
                Rule::in([
                    'MoEYS national level',
                    'Provincial Office of Education',
                    'District Office of Education',
                    'Teacher Education Institution',
                    'specific school or cluster',
                    'other government ministry',
                ]),
            ],
            'agreements.*.status' => [
                $requiredIfSubmitted,
                Rule::in(['active', 'expired', 'under renewal', 'under negotiation']),
            ],
            'agreements.*.institution_name' => [$requiredIfSubmitted, 'string', 'max:255'],
            'agreements.*.nature' => [
                $requiredIfSubmitted,
                Rule::in([
                    'MoU',
                    'Letter of Understanding',
                    'official approval letter',
                    'informal working arrangement',
                ]),
            ],
        ];
    }
}