<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Crash extends Model
{
    protected $table = 'crashes';

    protected $guarded = [];

    protected $casts = [
        'raw_payload' => 'array',
        'synced_at' => 'datetime',
        'crash_year' => 'integer',
        'speed_limit_kmh' => 'integer',
    ];
}
