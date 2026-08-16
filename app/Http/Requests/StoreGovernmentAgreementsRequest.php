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

        return [
            'agreements' => ['present', 'array'],
            'agreements.*.id' => [
                'nullable',
                'integer',
                Rule::exists('government_agreements', 'id')
                    ->where('programme_entry_id', $programmeEntry->id),
            ],
            'agreements.*.counterpart_agency' => [
                'required',
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
                'required',
                Rule::in(['active', 'expired', 'under renewal', 'under negotiation']),
            ],
            'agreements.*.institution_name' => ['required', 'string', 'max:255'],
            'agreements.*.nature' => [
                'required',
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