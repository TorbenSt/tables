<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapOccluder extends Model
{
    protected $fillable = [
        'map_site_id',
        'kind',
        'source',
        'osm_id',
        'name',
        'height_m',
        'radius_m',
        'polygon',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'height_m' => 'float',
            'radius_m' => 'float',
            'polygon' => 'array',
            'meta' => 'array',
        ];
    }

    public function site(): BelongsTo
    {
        return $this->belongsTo(MapSite::class, 'map_site_id');
    }
}
