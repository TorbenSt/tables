<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MapSite extends Model
{
    protected $fillable = [
        'venue_id',
        'title',
        'address',
        'latitude',
        'longitude',
        'zoom',
        'imagery_source',
        'status',
        'error_message',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'zoom' => 'int',
            'meta' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(MapTable::class)->orderBy('sort_order');
    }

    public function occluders(): HasMany
    {
        return $this->hasMany(MapOccluder::class);
    }

    public function displayTitle(): string
    {
        return $this->title ?: ($this->address ?: 'Kartenstandort');
    }
}
