<?php

namespace App\Services\Decisions;

use App\Models\SurveyAnswer;
use Illuminate\Support\Collection;

class SurveyAggregator
{
    /**
     * Average release percentages per scenario.
     *
     * @return array<int, array{scenario_id: int, outdoor_sun: float, outdoor_shade: float, indoor: float, n: int}>
     */
    public function profile(): array
    {
        $grouped = SurveyAnswer::query()
            ->selectRaw('scenario_id, AVG(outdoor_sun) as outdoor_sun, AVG(outdoor_shade) as outdoor_shade, AVG(indoor) as indoor, COUNT(*) as n')
            ->groupBy('scenario_id')
            ->orderBy('scenario_id')
            ->get();

        if ($grouped->isEmpty()) {
            return $this->defaultProfile();
        }

        return $grouped->map(fn ($row) => [
            'scenario_id' => (int) $row->scenario_id,
            'outdoor_sun' => round((float) $row->outdoor_sun, 1),
            'outdoor_shade' => round((float) $row->outdoor_shade, 1),
            'indoor' => round((float) $row->indoor, 1),
            'n' => (int) $row->n,
        ])->keyBy('scenario_id')->all();
    }

    public function responseCount(): int
    {
        return (int) \App\Models\SurveyResponse::query()->count();
    }

    /**
     * Sensible PoC defaults when no survey answers exist yet.
     *
     * @return array<int, array{scenario_id: int, outdoor_sun: float, outdoor_shade: float, indoor: float, n: int}>
     */
    public function defaultProfile(): array
    {
        $defaults = [
            1 => [40, 90, 100],
            2 => [80, 90, 100],
            3 => [30, 60, 100],
            4 => [60, 85, 100],
            5 => [20, 70, 100],
            6 => [5, 30, 100],
            7 => [0, 0, 100],
            8 => [20, 40, 100],
            9 => [40, 50, 100],
            10 => [100, 100, 100],
        ];

        $out = [];
        foreach ($defaults as $id => [$sun, $shade, $indoor]) {
            $out[$id] = [
                'scenario_id' => $id,
                'outdoor_sun' => (float) $sun,
                'outdoor_shade' => (float) $shade,
                'indoor' => (float) $indoor,
                'n' => 0,
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, SurveyAnswer>  $answers
     * @return array<int, array{outdoor_sun: int, outdoor_shade: int, indoor: int}>
     */
    public function fromAnswers(Collection $answers): array
    {
        return $answers->keyBy('scenario_id')->map(fn (SurveyAnswer $a) => [
            'outdoor_sun' => $a->outdoor_sun,
            'outdoor_shade' => $a->outdoor_shade,
            'indoor' => $a->indoor,
        ])->all();
    }
}
