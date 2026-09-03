<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TablePhoto extends Model
{
    protected $fillable = [
        'photo_session_id',
        'path',
        'captured_at',
        'latitude',
        'longitude',
        'bearing',
        'umbrella_hint',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'latitude' => 'float',
            'longitude' => 'float',
            'bearing' => 'float',
            'umbrella_hint' => 'boolean',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'photo_session_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(TableObservation::class);
    }

    public function detectedTables(): HasMany
    {
        return $this->hasMany(DetectedTable::class);
    }

    public function url(): string
    {
        return route('media', ['path' => $this->path], absolute: false);
    }

    /** Uhrzeit im 24h-Format, z. B. 14:30 */
    public function capturedAtHm(): string
    {
        return substr((string) $this->captured_at, 0, 5);
    }
}
