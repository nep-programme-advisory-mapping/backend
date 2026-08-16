<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdviserSubmissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Enforced by the route's permission:advisory.create middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'submitting_party'      => ['required', 'string', 'max:255'],
            'document_name'         => ['required', 'string', 'max:255'],
            'analysis_scope'        => ['nullable', 'string', 'in:full map,geographic subset,thematic subset'],
            'analysis_scope_detail' => ['nullable', 'string', 'max:1000'],
            'coordinator_id'        => ['nullable', 'integer', 'exists:users,id'],
            'programme_entry_id'    => ['nullable', 'integer', 'exists:programme_entries,id'],
            'status'                => ['nullable', 'string', 'max:255'],
            'document'              => ['nullable', 'file', 'mimes:pdf,txt', 'max:20480'],
        ];
    }

    public function messages(): array
    {
        return [
            'submitting_party.required' => 'The submitting party is required.',
            'document_name.required' => 'The document name is required.',
            'analysis_scope.in' => 'The analysis scope must be one of: full map, geographic subset, thematic subset.',
            'analysis_scope_detail.required' => 'Analysis scope detail is required for geographic or thematic subsets.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Trim whitespace from string inputs
        if ($this->has('submitting_party')) {
            $this->merge([
                'submitting_party' => trim($this->submitting_party),
            ]);
        }

        if ($this->has('document_name')) {
            $this->merge([
                'document_name' => trim($this->document_name),
            ]);
        }

        if ($this->has('analysis_scope_detail')) {
            $this->merge([
                'analysis_scope_detail' => trim($this->analysis_scope_detail),
            ]);
        }

        if ($this->has('status')) {
            $this->merge([
                'status' => trim($this->status),
            ]);
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // If analysis scope is geographic or thematic subset, detail is required
            $analysisScope = $this->input('analysis_scope', 'full map');

            if (in_array($analysisScope, ['geographic subset', 'thematic subset'])) {
                if (empty($this->input('analysis_scope_detail'))) {
                    $validator->errors()->add(
                        'analysis_scope_detail',
                        'Analysis scope detail is required for geographic or thematic subsets.'
                    );
                }
            }

            // Block if this programme entry already has an advisory note by another coordinator
            $programmeEntryId = $this->input('programme_entry_id');
            $requestedCoordinatorId = $this->input('coordinator_id') ?? $this->user()?->id;

            if ($programmeEntryId) {
                $existing = \App\Models\AdvisoryNote::where('programme_entry_id', $programmeEntryId)
                    ->whereNotNull('coordinator_id')
                    ->first();

                if ($existing && $existing->coordinator_id !== (int) $requestedCoordinatorId) {
                    $validator->errors()->add(
                        'programme_entry_id',
                        'This programme entry has already been advised by another coordinator.'
                    );
                }
            }
        });
    }
}