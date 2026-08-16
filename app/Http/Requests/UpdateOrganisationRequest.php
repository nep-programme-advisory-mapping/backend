<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'contact_name' => $this->input('contact_name') ?? $this->input('contactName'),
            'member_since' => $this->input('member_since') ?? $this->input('memberSince'),
        ], fn($v) => $v !== null));
    }

    public function rules(): array
    {
        $organisationId = $this->route('organisation')->id;

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'contact_name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('organisations', 'email')->ignore($organisationId)],
            'member_since' => ['sometimes', 'required', 'integer', 'min:1900', 'max:' . (int) date('Y') + 1],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
        ];
    }
}