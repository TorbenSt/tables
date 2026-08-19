<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

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

    public function detectedTables(): HasMany
    {
        return $this->hasMany(DetectedTable::class);
    }

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}
