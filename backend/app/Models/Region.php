<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = [
        'ags', 'name', 'level', 'state_name', 'state_ags', 'district_ags',
        'parent_id', 'population', 'area_km2', 'lon', 'lat',
    ];

    public function accidents()
    {
        return $this->hasMany(Accident::class);
    }

    public function indicatorValues()
    {
        return $this->hasMany(IndicatorValue::class);
    }

    public function children()
    {
        return $this->hasMany(Region::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Region::class, 'parent_id');
    }
}
