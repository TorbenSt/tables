<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetectedTable extends Model
{
    protected $fillable = [
        'photo_session_id',
        'table_photo_id',
        'label',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
        'has_umbrella',
        'umbrella_height_m',
        'umbrella_radius_m',
        'observed_condition',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'bbox_x' => 'float',
            'bbox_y' => 'float',
            'bbox_w' => 'float',
            'bbox_h' => 'float',
            'has_umbrella' => 'boolean',
            'umbrella_height_m' => 'float',
            'umbrella_radius_m' => 'float',
            'meta' => 'array',
        ];
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(PhotoSession::class, 'photo_session_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(TablePhoto::class, 'table_photo_id');
    }

    public function forecasts(): HasMany
    {
        return $this->hasMany(SunShadeForecast::class);
    }
}
