<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IndicatorValue extends Model
{
    protected $fillable = [
        'region_id',
        'indicator_id',
        'year',
        'value',
    ];

    protected $casts = [
        'value' => 'decimal:4',
    ];

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class);
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}
