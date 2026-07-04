<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccidentResource extends JsonResource
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
            'id'                   => $this->id,
            'year'                 => (int) $this->year,
            'month'                => $this->month !== null ? (int) $this->month : null,
            'hour'                 => $this->hour !== null ? (int) $this->hour : null,
            'weekday'              => $this->weekday !== null ? (int) $this->weekday : null,
            'category'             => $this->category !== null ? (int) $this->category : null,
            'accident_type'        => $this->accident_type,
            'crash_type'           => $this->crash_type,           // UTYP1: 1=single-vehicle … 7=other
            'light_condition'      => $this->light_condition,
            'road_condition'       => $this->road_condition,       // IstStrassenzustand: 0=dry, 1=wet, 2=icy (2022+)
            'involves_pedestrian'  => (bool) $this->involves_pedestrian,
            'involves_bicycle'     => (bool) $this->involves_bicycle,
            'involves_motorcycle'  => (bool) $this->involves_motorcycle,
            'involves_car'         => (bool) $this->involves_car,
            'involves_truck'       => (bool) $this->involves_truck,
            'lon'                  => $this->lon !== null ? (float) $this->lon : null,
            'lat'                  => $this->lat !== null ? (float) $this->lat : null,
            'district_ags'         => $this->district_ags,
            'municipality_ags'     => $this->municipality_ags,
            'region_id'            => $this->region_id,
            'source_system'        => $this->source_system,
            'import_run_id'        => $this->import_run_id,
            'created_at'           => $this->created_at?->toIso8601String(),
            'updated_at'           => $this->updated_at?->toIso8601String(),
        ];
    }
}
