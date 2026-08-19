<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PhotoSession extends Model
{
    protected $fillable = [
        'venue_id',
        'title',
        'capture_date',
        'status',
        'error_message',
        'analysis_raw',
    ];

    protected function casts(): array
    {
        return [
            'capture_date' => 'date',
            'analysis_raw' => 'array',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(TablePhoto::class)->orderBy('sort_order')->orderBy('captured_at');
    }

    public function detectedTables(): HasMany
    {
        return $this->hasMany(DetectedTable::class);
    }
}
