<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoadSegment extends Model
{
    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
        'aadt' => 'integer',
        'speed_limit_kmh' => 'integer',
        'heavy_vehicle_pct' => 'float',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
