<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportRun extends Model
{
    protected $fillable = [
        'data_source_id',
        'status',
        'records_inserted',
        'records_updated',
        'records_skipped',
        'source_url',
        'source_snapshot',
        'retrieved_at',
        'notes',
    ];

    protected $casts = [
        'retrieved_at' => 'datetime',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
