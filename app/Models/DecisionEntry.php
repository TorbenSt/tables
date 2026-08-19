<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DecisionEntry extends Model
{
    protected $fillable = [
        'decision_run_id',
        'target_date',
        'matched_scenario_id',
        'outdoor_sun',
        'outdoor_shade',
        'indoor',
        'weather_day',
        'reasoning',
        'grok_adjusted',
    ];

    protected function casts(): array
    {
        return [
            'target_date' => 'date',
            'weather_day' => 'array',
            'grok_adjusted' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(DecisionRun::class, 'decision_run_id');
    }

    public function releaseFor(string $category): int
    {
        return match ($category) {
            'outdoor_sun' => $this->outdoor_sun,
            'outdoor_shade' => $this->outdoor_shade,
            'indoor' => $this->indoor,
            default => 0,
        };
    }
}
