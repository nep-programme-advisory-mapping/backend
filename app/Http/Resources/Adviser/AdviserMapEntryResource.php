<?php

namespace App\Http\Resources\Adviser;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdviserMapEntryResource extends JsonResource
{
    /**
     * Transform the ProgrammeEntry into a clean API response for the Adviser overlap query.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programme_name' => $this->programme_name,
            'organisation' => $this->whenLoaded('organisation', fn () => [
                'id' => $this->organisation->id,
                'name' => $this->organisation->name,
            ]),
            'budget_band' => $this->whenLoaded('budgetBand', fn () => [
                'id' => $this->budgetBand->id,
                'label' => $this->budgetBand->label,
            ]),
            'start_year' => $this->start_year,
            'end_year' => $this->end_year,
            'ongoing' => (bool) $this->ongoing,
            'fte_staff' => (float) $this->fte_staff,
            'direct_beneficiaries' => $this->direct_beneficiaries,
            'indirect_beneficiaries' => $this->indirect_beneficiaries,
            'method' => $this->method,
            'verified_date' => $this->verified_date?->format('Y-m-d'),
            'keywords' => $this->whenLoaded('keywords', fn () =>
                $this->keywords->pluck('keyword')
            ),
            'locations' => $this->whenLoaded('locations', fn () =>
                $this->locations->map(fn ($loc) => [
                    'id' => $loc->id,
                    'province' => $loc->province ? [
                        'id' => $loc->province->id,
                        'name' => $loc->province->province_name,
                    ] : null,
                    'district' => $loc->district ? [
                        'id' => $loc->district->id,
                        'name' => $loc->district->name,
                    ] : null,
                    'commune' => $loc->commune ? [
                        'id' => $loc->commune->id,
                        'name' => $loc->commune->name,
                    ] : null,
                    'village' => $loc->village ? [
                        'id' => $loc->village->id,
                        'name' => $loc->village->name,
                    ] : null,
                ])
            ),
            'activities' => $this->whenLoaded('activities', fn () =>
                $this->activities->map(fn ($activity) => [
                    'id' => $activity->id,
                    'is_primary' => (bool) $activity->is_primary,
                    'inclusion_group' => $activity->inclusion_group,
                    'inclusion_type' => $activity->inclusion_type,
                    'taxonomy' => $this->formatTaxonomy($activity),
                    'education_levels' => $activity->activityLevels->map(fn ($level) => [
                        'id' => $level->educationLevel?->id,
                        'name' => $level->educationLevel?->level_name,
                    ])->filter(fn ($l) => $l['id'] !== null)->values(),
                ])
            ),
        ];
    }

  
    private function formatTaxonomy($activity): ?array
    {
        $item = $activity->activityItem;
        if (! $item) {
            return null;
        }

        $subcategory = $item->subcategory;
        $category = $subcategory?->category;

        return [
            'category' => $category ? [
                'id' => $category->id,
                'name' => $category->label,
            ] : null,
            'subcategory' => $subcategory ? [
                'id' => $subcategory->id,
                'name' => $subcategory->label,
            ] : null,
            'item' => [
                'id' => $item->id,
                'name' => $item->label,
            ],
        ];
    }
}