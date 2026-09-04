<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Venue extends Model
{
    protected $fillable = [
        'name',
        'latitude',
        'longitude',
        'tables_indoor',
        'tables_outdoor_sun',
        'tables_outdoor_shade',
        'timezone',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
        ];
    }

    public function weatherSnapshots(): HasMany
    {
        return $this->hasMany(WeatherSnapshot::class);
    }

    public function decisionRuns(): HasMany
    {
        return $this->hasMany(DecisionRun::class);
    }

    public function photoSessions(): HasMany
    {
        return $this->hasMany(PhotoSession::class);
    }

    public function mapSites(): HasMany
    {
        return $this->hasMany(MapSite::class);
    }

    public function capacityFor(string $category): int
    {
        return match ($category) {
            'outdoor_sun' => $this->tables_outdoor_sun,
            'outdoor_shade' => $this->tables_outdoor_shade,
            'indoor' => $this->tables_indoor,
            default => 0,
        };
    }
}
