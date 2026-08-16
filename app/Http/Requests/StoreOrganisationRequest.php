<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganisationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // role:nep_admin already enforced at route level
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:organisations,email'],
            'member_since' => ['required', 'integer', 'min:1900', 'max:' . (int) date('Y') + 1],
            'status' => ['sometimes', Rule::in(['active', 'inactive'])],
            'logo' => ['sometimes', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ];
    }
}