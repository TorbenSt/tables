<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapTable extends Model
{
    protected $fillable = [
        'map_site_id',
        'stable_key',
        'color_hex',
        'label',
        'latitude',
        'longitude',
        'has_umbrella',
        'umbrella_height_m',
        'umbrella_radius_m',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'has_umbrella' => 'boolean',
            'umbrella_height_m' => 'float',
            'umbrella_radius_m' => 'float',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MapSite::class, 'map_site_id');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(MapSunShadeForecast::class);
    }

    public function displayLabel(): string
    {
        $key = $this->stable_key ? $this->stable_key.' · ' : '';

        return $key.($this->label ?: 'Außentisch');
    }
}
