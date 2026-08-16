<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAdviserProgrammeEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Enforced by the route's permission:advisory.update middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'organisation_id'                    => 'required|integer|exists:organisations,id',
            'programme_name'                     => 'required|string|max:255',
            'start_year'                         => 'required|integer|min:1900|max:2100',
            'end_year'                           => 'nullable|integer|min:1900|max:2100|gte:start_year',
            'ongoing'                            => 'sometimes|boolean',
            'fte_staff'                          => 'nullable|numeric|min:0',
            'direct_beneficiaries'               => 'nullable|integer|min:0',
            'indirect_beneficiaries'             => 'nullable|integer|min:0',
            'method'                             => 'nullable|string',
            'budget_band_id'                     => 'nullable|integer|exists:budget_bands,id',
            'activities'                         => 'sometimes|array',
            'activities.*.activity_item_id'      => 'required_with:activities|integer|exists:activity_items,id',
            'activities.*.is_primary'            => 'sometimes|boolean',
            'activities.*.education_level_ids'   => 'sometimes|array',
            'activities.*.education_level_ids.*' => 'integer|exists:education_levels,id',
            'activities.*.inclusion_group'       => 'nullable|string',
            'activities.*.inclusion_type'        => 'nullable|string',
            'geography'                          => 'sometimes|array',
            'geography.province_ids'             => 'sometimes|array',
            'geography.province_ids.*'           => 'integer|exists:provinces,id',
            'geography.other_countries'          => 'sometimes|array',
            'geography.other_countries.*'        => 'string',
            'keywords'                           => 'sometimes|array|max:5',
            'keywords.*'                         => 'string|max:100',
        ];
    }

    public function failedAuthorization()
    {
        abort(403, 'Forbidden.');
    }
}
