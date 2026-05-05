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
        'speed_limit_kmh' => 'integer',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
