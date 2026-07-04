<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Indicator extends Model
{
    protected $fillable = [
        'code',
        'name',
        'unit',
        'source_system',
    ];

    public function indicatorValues(): HasMany
    {
        return $this->hasMany(IndicatorValue::class);
    }
}
