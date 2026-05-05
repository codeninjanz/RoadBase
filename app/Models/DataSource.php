<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DataSource extends Model
{
    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function countSites(): HasMany
    {
        return $this->hasMany(CountSite::class);
    }

    public function roadSegments(): HasMany
    {
        return $this->hasMany(RoadSegment::class);
    }

    public function syncRuns(): HasMany
    {
        return $this->hasMany(SyncRun::class);
    }

    public function isStale(): bool
    {
        return $this->last_synced_at === null
            || $this->last_synced_at->lt(now()->subHours($this->update_frequency_hours));
    }
}
