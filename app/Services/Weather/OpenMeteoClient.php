<?php

namespace App\Services\Weather;

use App\Models\Venue;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenMeteoClient
{
    /**
     * Fetch 14-day forecast with daily + hourly precipitation for rain-timing scenarios.
     *
     * @return array<string, mixed>
     */
    public function forecast(Venue $venue, int $forecastDays = 14): array
    {
        $base = rtrim((string) config('services.open_meteo.base_url'), '/');
        $timezone = $venue->timezone ?: config('services.open_meteo.timezone', 'Europe/Berlin');

        $response = Http::timeout(30)->get($base.'/forecast', [
            'latitude' => $venue->latitude,
            'longitude' => $venue->longitude,
            'timezone' => $timezone,
            'forecast_days' => $forecastDays,
            'daily' => implode(',', [
                'weathercode',
                'temperature_2m_max',
                'temperature_2m_min',
                'precipitation_sum',
                'precipitation_probability_max',
                'windspeed_10m_max',
                'sunshine_duration',
            ]),
            'hourly' => implode(',', [
                'precipitation',
                'precipitation_probability',
                'weathercode',
                'temperature_2m',
                'cloudcover',
            ]),
        ]);

        if (! $response->successful()) {
            throw new RuntimeException('Open-Meteo Fehler: '.$response->status());
        }

        return $response->json();
    }

    /**
     * Normalize API payload into per-day structures.
     *
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    public function daysFromPayload(array $payload): array
    {
        $daily = $payload['daily'] ?? [];
        $dates = $daily['time'] ?? [];
        $hours = $payload['hourly']['time'] ?? [];
        $hourlyPrecip = $payload['hourly']['precipitation'] ?? [];
        $hourlyCloud = $payload['hourly']['cloudcover'] ?? [];
        $hourlyTemp = $payload['hourly']['temperature_2m'] ?? [];

        $days = [];
        foreach ($dates as $i => $date) {
            $morningRain = 0.0;
            $afternoonRain = 0.0;
            $avgCloud = [];
            $temps = [];

            foreach ($hours as $h => $iso) {
                if (! str_starts_with($iso, $date)) {
                    continue;
                }
                $hour = (int) substr($iso, 11, 2);
                $precip = (float) ($hourlyPrecip[$h] ?? 0);
                if ($hour < 14) {
                    $morningRain += $precip;
                } else {
                    $afternoonRain += $precip;
                }
                if (isset($hourlyCloud[$h])) {
                    $avgCloud[] = (float) $hourlyCloud[$h];
                }
                if (isset($hourlyTemp[$h])) {
                    $temps[] = (float) $hourlyTemp[$h];
                }
            }

            $days[] = [
                'date' => $date,
                'weathercode' => (int) ($daily['weathercode'][$i] ?? 0),
                'temp_max' => (float) ($daily['temperature_2m_max'][$i] ?? 0),
                'temp_min' => (float) ($daily['temperature_2m_min'][$i] ?? 0),
                'precipitation_sum' => (float) ($daily['precipitation_sum'][$i] ?? 0),
                'precipitation_probability_max' => (int) ($daily['precipitation_probability_max'][$i] ?? 0),
                'windspeed_max' => (float) ($daily['windspeed_10m_max'][$i] ?? 0),
                'sunshine_duration' => (float) ($daily['sunshine_duration'][$i] ?? 0),
                'morning_rain_mm' => round($morningRain, 2),
                'afternoon_rain_mm' => round($afternoonRain, 2),
                'avg_cloudcover' => $avgCloud ? round(array_sum($avgCloud) / count($avgCloud), 1) : null,
                'avg_temp' => $temps ? round(array_sum($temps) / count($temps), 1) : null,
            ];
        }

        return $days;
    }
}
