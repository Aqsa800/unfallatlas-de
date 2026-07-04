<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RegionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'ags'          => $this->ags,
            'name'         => $this->name,
            'level'        => $this->level,
            'state_name'   => $this->state_name,
            'state_ags'    => $this->state_ags,
            'district_ags' => $this->district_ags,
            'population'   => $this->population,
            'area_km2'     => $this->area_km2 !== null ? (float) $this->area_km2 : null,
            'lon'          => $this->lon !== null ? (float) $this->lon : null,
            'lat'          => $this->lat !== null ? (float) $this->lat : null,

            // Optional extras included only when loaded
            'children_count' => $this->when(
                isset($this->children_count),
                $this->children_count
            ),
            'indicator_values' => $this->when(
                $this->relationLoaded('indicatorValues'),
                fn () => $this->indicatorValues->map(fn ($iv) => [
                    'indicator_code' => $iv->indicator?->code ?? $iv->indicator_code,
                    'indicator_name' => $iv->indicator?->name,
                    'year'           => (int) $iv->year,
                    'value'          => (float) $iv->value,
                    'unit'           => $iv->indicator?->unit ?? $iv->unit,
                ])->sortBy(['indicator_code','year'])->values()
            ),
        ];
    }
}
