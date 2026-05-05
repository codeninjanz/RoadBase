<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CountSite extends Model
{
    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'count_date' => 'date',
        'synced_at' => 'datetime',
        'aadt' => 'integer',
        'speed_limit_kmh' => 'integer',
        'peak_hour_volume' => 'integer',
        'heavy_vehicle_pct' => 'float',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }

    public function lat(): ?float
    {
        return $this->getAttribute('lat');
    }

    public function lng(): ?float
    {
        return $this->getAttribute('lng');
    }
}
