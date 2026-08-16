<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MapEntryFlatResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programme_name' => $this->programme_name,
            'organisation_name' => $this->whenLoaded('organisation', fn () =>
                $this->organisation->name
            ),
            'is_unverified' => (bool) $this->is_unverified,
            'activities' => $this->whenLoaded('activities', fn () =>
                $this->activities->map(fn ($a) => [
                    'is_primary' => (bool) $a->is_primary,
                    'code' => $a->activityItem?->code,
                    'inclusion_group' => $a->inclusion_group,
                    'activity_levels' => $a->activityLevels->map(fn ($l) => [
                        'education_level_id' => $l->education_level_id,
                    ]),
                ])
            ),
            'audiences' => $this->whenLoaded('activities', fn () =>
                $this->activities
                    ->map(fn ($a) => [
                        'inclusion_group' => $a->inclusion_group,
                        'inclusion_type' => $a->inclusion_type,
                    ])
                    ->filter(fn ($aud) => $aud['inclusion_group'] !== null || $aud['inclusion_type'] !== null)
                    ->unique()
                    ->values()
            ),
            'provinces' => $this->whenLoaded('locations', fn () =>
                $this->locations
                    ->map(fn ($loc) => $loc->province?->province_name)
                    ->filter()
                    ->unique()
                    ->values()
            ),
            'locations' => $this->whenLoaded('locations', fn () =>
                $this->locations->map(fn ($loc) => [
                    'district' => $loc->district ? ['name' => $loc->district->name] : null,
                    'village' => $loc->village ? ['name' => $loc->village->name] : null,
                ])
            ),
            'budget_band' => $this->whenLoaded('budgetBand', fn () =>
                $this->budgetBand?->label
            ),
            'budget_band_id' => $this->budget_band_id,
            'government_agreements' => $this->whenLoaded('governmentAgreements', fn () =>
                $this->governmentAgreements->map(fn ($ga) => [
                    'counterpart_agency' => $ga->counterpart_agency,
                ])
            ),
        ];
    }
}
