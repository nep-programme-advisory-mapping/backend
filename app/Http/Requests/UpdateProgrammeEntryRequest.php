<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateProgrammeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A publish attempt doesn't require the client to resend every Section 1
     * field in the same request — the frontend always does today, but the
     * backend shouldn't assume that. Before validating, backfill any
     * Section 1 field the request omitted with the entry's already-persisted
     * value, so "required" checks whether the entry *as a whole* is
     * complete rather than whether this one request happened to include it.
     */
    protected function prepareForValidation(): void
    {
        if (!$this->boolean('is_submitted')) {
            return;
        }

        $entry = $this->route('programmeEntry');
        if (!$entry) {
            return;
        }

        $section1Fields = [
            'programme_name', 'start_year', 'end_year', 'ongoing',
            'fte_staff', 'budget_band_id', 'direct_beneficiaries', 'indirect_beneficiaries',
        ];

        $defaults = [];
        foreach ($section1Fields as $field) {
            if (!$this->has($field)) {
                $defaults[$field] = $entry->{$field};
            }
        }

        if ($defaults) {
            $this->merge($defaults);
        }
    }

    public function rules(): array
    {
        // Autosave/draft updates hit this same endpoint constantly with
        // partial data and must never trip "required" validation — only an
        // explicit publish attempt (is_submitted sent truthy) enforces the
        // full Section 1 field set. This mirrors the frontend's own
        // `isSection1Complete` check (programmeIdentity.ts) so client and
        // server agree on what "complete" means, and StoreProgrammeEntryRequest
        // applies the same rule for brand-new entries.
        $isPublishing = $this->boolean('is_submitted');
        $requiredWhenPublishing = $isPublishing ? ['required'] : ['sometimes', 'nullable'];

        return [
            'budget_band_id' => [...$requiredWhenPublishing, 'exists:budget_bands,id'],
            'programme_name' => [...$requiredWhenPublishing, 'string', 'max:255'],
            'start_year' => [...$requiredWhenPublishing, 'integer', 'min:1900', 'max:2100'],
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

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if (!$this->boolean('is_submitted')) {
                return;
            }

            if (!$this->boolean('ongoing') && !$this->filled('end_year')) {
                $validator->errors()->add('end_year', 'The end year field is required unless the programme is ongoing.');
            }

            // Section 1 fields are checked field-by-field above, but
            // "publishable" also depends on the other sections (activities,
            // geography, keywords) already saved against this entry —
            // matches the frontend's hasCompletedAllRequired computed
            // (programmeForm.ts). These are saved via separate endpoints, so
            // this only sees what's already persisted from prior autosaves;
            // a same-request "publish on the very first save" only works if
            // the client sends Section 1 last (which the wizard's own
            // Step 1→5 flow, plus continuous autosave, guarantees in
            // practice).
            $entry = $this->route('programmeEntry');
            if (!$entry) {
                return;
            }

            if (!$entry->activities()->exists()) {
                $validator->errors()->add('activities', 'At least one activity must be selected before publishing.');
            }

            $hasGeography = $entry->locations()->exists();
            if (!$hasGeography) {
                $validator->errors()->add('provinces', 'At least one province or country must be selected before publishing.');
            }

            if (!$entry->keywords()->exists()) {
                $validator->errors()->add('keywords', 'At least one keyword must be added before publishing.');
            }
        });
    }
}