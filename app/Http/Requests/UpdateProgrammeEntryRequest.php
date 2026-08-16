<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProgrammeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'budget_band_id' => ['nullable', 'exists:budget_bands,id'],
            'programme_name' => ['sometimes', 'required', 'string', 'max:255'],
            'start_year' => ['sometimes', 'required', 'integer', 'min:1900', 'max:2100'],
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
}