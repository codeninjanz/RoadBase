<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rca extends Model
{
    protected $guarded = [];

    protected $casts = [
        'contact' => 'array',
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
    ];

    public function dataSource(): BelongsTo
    {
        return $this->belongsTo(DataSource::class);
    }
}
