<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreEntryCollaborationsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'collaborations' => ['present', 'array', 'max:5'],
            'collaborations.*' => ['required', 'string', 'max:255'],
        ];
    }
}
