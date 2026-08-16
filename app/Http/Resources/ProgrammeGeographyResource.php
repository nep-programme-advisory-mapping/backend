<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProgrammeGeographyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'programme_entry_id' => $this->programme_entry_id,
            'province' => $this->whenLoaded('province', fn () => $this->province ? [
                'id' => $this->province->id,
                'name' => $this->province->province_name,
            ] : null),
            'district' => $this->whenLoaded('district', fn () => $this->district ? [
                'id' => $this->district->id,
                'name' => $this->district->name,
            ] : null),
            'commune' => $this->whenLoaded('commune', fn () => $this->commune ? [
                'id' => $this->commune->id,
                'name' => $this->commune->name,
            ] : null),
            'village' => $this->whenLoaded('village', fn () => $this->village ? [
                'id' => $this->village->id,
                'name' => $this->village->name,
            ] : null),
            'country' => $this->country,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}