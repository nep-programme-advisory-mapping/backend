<?php

namespace App\Http\Requests;

use App\Models\Commune;
use App\Models\District;
use App\Models\Village;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreProgrammeGeographyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'provinces' => ['present', 'array'],
            'provinces.*.province_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('provinces', 'id'),
            ],
            'provinces.*.district_ids' => ['sometimes', 'array'],
            'provinces.*.district_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('districts', 'id'),
            ],
            'provinces.*.commune_ids' => ['sometimes', 'array'],
            'provinces.*.commune_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('communes', 'id'),
            ],
            'provinces.*.village_ids' => ['sometimes', 'array'],
            'provinces.*.village_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('villages', 'id'),
            ],

            'other_countries' => ['present', 'array'],
            'other_countries.*' => ['required', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'provinces.*.province_id.exists' => 'The selected province is invalid.',
            'provinces.*.district_ids.*.exists' => 'One or more selected districts are invalid.',
            'provinces.*.commune_ids.*.exists' => 'One or more selected communes are invalid.',
            'provinces.*.village_ids.*.exists' => 'One or more selected villages are invalid.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            foreach ($this->input('provinces', []) as $index => $provinceData) {
                $provinceId = $provinceData['province_id'] ?? null;
                $districtIds = $provinceData['district_ids'] ?? [];

                if (empty($districtIds) || ! $provinceId) {
                    continue;
                }

                $validCount = District::where('province_id', $provinceId)
                    ->whereIn('id', $districtIds)
                    ->count();

                if ($validCount !== count(array_unique($districtIds))) {
                    $validator->errors()->add(
                        "provinces.{$index}.district_ids",
                        'One or more selected districts do not belong to the selected province.'
                    );
                }

                $communeIds = $provinceData['commune_ids'] ?? [];
                if (!empty($communeIds) && !empty($districtIds)) {
                    $validCommuneCount = Commune::whereIn('district_id', $districtIds)
                        ->whereIn('id', $communeIds)
                        ->count();
                    if ($validCommuneCount !== count(array_unique($communeIds))) {
                        $validator->errors()->add(
                            "provinces.{$index}.commune_ids",
                            'One or more selected communes do not belong to the selected districts.'
                        );
                    }
                }

                $villageIds = $provinceData['village_ids'] ?? [];
                if (!empty($villageIds) && !empty($communeIds)) {
                    $validVillageCount = Village::whereIn('commune_id', $communeIds)
                        ->whereIn('id', $villageIds)
                        ->count();
                    if ($validVillageCount !== count(array_unique($villageIds))) {
                        $validator->errors()->add(
                            "provinces.{$index}.village_ids",
                            'One or more selected villages do not belong to the selected communes.'
                        );
                    }
                }
            }
        });
    }
}
