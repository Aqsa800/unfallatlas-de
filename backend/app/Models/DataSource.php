<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $fillable = [
        'key',
        'name',
        'url',
        'license',
        'license_url',
        'publisher',
        'description',
    ];

    public function importRuns(): HasMany
    {
        return $this->hasMany(ImportRun::class);
    }
}
