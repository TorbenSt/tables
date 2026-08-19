<?php

namespace App\Services\Decisions;

/**
 * Deterministic mapping from Open-Meteo day stats to one of the 10 survey scenarios.
 */
class ScenarioMatcher
{
    /**
     * @param  array<string, mixed>  $day
     */
    public function match(array $day): int
    {
        $tempMax = (float) ($day['temp_max'] ?? 0);
        $precip = (float) ($day['precipitation_sum'] ?? 0);
        $morningRain = (float) ($day['morning_rain_mm'] ?? 0);
        $afternoonRain = (float) ($day['afternoon_rain_mm'] ?? 0);
        $wind = (float) ($day['windspeed_max'] ?? 0);
        $cloud = (float) ($day['avg_cloudcover'] ?? 50);
        $sunshine = (float) ($day['sunshine_duration'] ?? 0);
        $code = (int) ($day['weathercode'] ?? 0);

        $isRainyCode = $code >= 51;
        $significantRain = $precip >= 2.0 || $isRainyCode;

        if ($significantRain) {
            if ($morningRain >= 1.5 && $afternoonRain < 0.5) {
                return 8;
            }
            if ($afternoonRain >= 1.5 && $morningRain < 0.5) {
                return 9;
            }
            if ($precip >= 1.0 || ($morningRain + $afternoonRain) >= 2.0) {
                return 7;
            }
        }

        // Ideal mild sunny day
        if ($tempMax >= 22 && $tempMax <= 24 && $cloud < 40 && $sunshine > 5 * 3600 && $wind < 25 && $precip < 0.5) {
            return 10;
        }

        // Hot blazing sun
        if ($tempMax >= 30 && $cloud < 35 && $precip < 0.5) {
            return 1;
        }

        // Pleasant sunny 24-28
        if ($tempMax >= 24 && $tempMax < 30 && $cloud < 40 && $precip < 0.5) {
            return 2;
        }

        // Sunny but cool/windy
        if ($tempMax >= 18 && $tempMax <= 22 && $cloud < 45 && ($wind >= 25 || $tempMax <= 20) && $precip < 0.5) {
            return 3;
        }

        // Lightly cloudy / changeable ~26
        if ($tempMax >= 24 && $tempMax <= 28 && $cloud >= 40 && $cloud < 70 && $precip < 1.0) {
            return 4;
        }

        // Overcast warm dry
        if ($tempMax >= 24 && $tempMax <= 27 && $cloud >= 70 && $precip < 1.0) {
            return 5;
        }

        // Overcast cool grey
        if ($tempMax >= 16 && $tempMax <= 20 && $cloud >= 60 && $precip < 1.0) {
            return 6;
        }

        // Fallbacks by temperature / cloud
        if ($tempMax >= 28 && $precip < 1.0) {
            return $cloud < 50 ? 1 : 4;
        }
        if ($tempMax >= 24 && $precip < 1.0) {
            return $cloud < 50 ? 2 : 5;
        }
        if ($tempMax >= 18 && $precip < 1.0) {
            return $cloud < 50 ? 3 : 6;
        }

        return $precip >= 1.0 ? 7 : 6;
    }
}
