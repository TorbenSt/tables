<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DecisionRun extends Model
{
    protected $fillable = [
        'venue_id',
        'weather_snapshot_id',
        'source',
        'ran_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'ran_at' => 'datetime',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function weatherSnapshot(): BelongsTo
    {
        return $this->belongsTo(WeatherSnapshot::class);
    }

    public function entries(): HasMany
    {
        return $this->hasMany(DecisionEntry::class);
    }
}
