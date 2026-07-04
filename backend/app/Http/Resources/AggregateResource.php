<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A lightweight pass-through resource for aggregate/computed responses.
 * The underlying $resource is expected to be an associative array.
 */
class AggregateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  Request  $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Cast stdClass objects (from DB::select / DB::table) to arrays
        if (is_object($this->resource)) {
            return (array) $this->resource;
        }

        return $this->resource;
    }
}
