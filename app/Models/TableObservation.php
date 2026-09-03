<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TableObservation extends Model
{
    protected $fillable = [
        'detected_table_id',
        'table_photo_id',
        'bbox_x',
        'bbox_y',
        'bbox_w',
        'bbox_h',
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
            'meta' => 'array',
        ];
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DetectedTable::class, 'detected_table_id');
    }

    public function photo(): BelongsTo
    {
        return $this->belongsTo(TablePhoto::class, 'table_photo_id');
    }
}
