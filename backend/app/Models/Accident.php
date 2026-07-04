<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Accident extends Model
{
    protected $fillable = [
        'year',
        'month',
        'hour',
        'weekday',
        'category',
        'accident_type',
        'light_condition',
        'road_condition',   // IstStrassenzustand: 0=dry, 1=wet, 2=icy (available 2022–2024)
        'crash_type',       // UTYP1 Unfalltyp: 1=single-vehicle, 2=turning, 3=crossing, 4=pedestrian, 5=parking, 6=in-lane, 7=other
        'involves_pedestrian',
        'involves_bicycle',
        'involves_motorcycle',
        'involves_car',
        'involves_truck',
        'lon',
        'lat',
        'district_ags',
        'municipality_ags',
        'region_id',
        'source_system',
        'import_run_id',
    ];

    protected $casts = [
        'involves_pedestrian'  => 'boolean',
        'involves_bicycle'     => 'boolean',
        'involves_motorcycle'  => 'boolean',
        'involves_car'         => 'boolean',
        'involves_truck'       => 'boolean',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function importRun(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class);
    }
}
