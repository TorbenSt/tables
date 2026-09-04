<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MapSunShadeForecast extends Model
{
    protected $fillable = [
        'map_table_id',
        'forecast_date',
        'hourly',
        'sun_hours',
        'shade_hours',
    ];

    protected function casts(): array
    {
        return [
            'forecast_date' => 'date',
            'hourly' => 'array',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(MapTable::class, 'map_table_id');
    }
}
