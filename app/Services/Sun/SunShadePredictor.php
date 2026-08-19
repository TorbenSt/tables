<?php

namespace App\Services\Sun;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\SunShadeForecast;
use App\Models\TablePhoto;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class SunShadePredictor
{
    public function __construct(private SunPositionService $sun) {}

    /**
     * Build exposure profile from observations and generate forecasts for a year (monthly sample days).
     *
     * @return Collection<int, SunShadeForecast>
     */
    public function generateForSession(PhotoSession $session): Collection
    {
        $session->load(['photos', 'detectedTables']);
        $lat = (float) ($session->photos->first()?->latitude ?? 52.52);
        $lng = (float) ($session->photos->first()?->longitude ?? 13.405);
        $tz = $session->venue?->timezone ?? 'Europe/Berlin';

        $profile = $this->buildExposureProfile($session);
        $created = collect();

        foreach ($session->detectedTables as $table) {
            $table->forecasts()->delete();

            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::create(null, $month, 15, 12, 0, 0, $tz);
                $forecast = $this->forecastDay($table, $date, $lat, $lng, $profile);
                $created->push($forecast);
            }

            // Also forecast the capture date itself for immediate demo
            $capture = Carbon::parse($session->capture_date->format('Y-m-d'), $tz)->startOfDay();
            if ($capture->day !== 15) {
                $created->push($this->forecastDay($table, $capture, $lat, $lng, $profile));
            }
        }

        return $created;
    }

    /**
     * @return array{sun_azimuths: array<int, float>, shade_azimuths: array<int, float>, bearings: array<int, float>, has_umbrella_bias: bool}
     */
    public function buildExposureProfile(PhotoSession $session): array
    {
        $sunAzimuths = [];
        $shadeAzimuths = [];
        $bearings = [];
        $hasUmbrellaBias = false;
        $tz = $session->venue?->timezone ?? 'Europe/Berlin';

        foreach ($session->detectedTables as $table) {
            if ($table->has_umbrella) {
                $hasUmbrellaBias = true;
            }
            /** @var TablePhoto|null $photo */
            $photo = $table->photo ?? $session->photos->firstWhere('id', $table->table_photo_id);
            if (! $photo) {
                continue;
            }
            $bearings[] = (float) $photo->bearing;

            $dt = Carbon::parse(
                $session->capture_date->format('Y-m-d').' '.$photo->captured_at,
                $tz
            );
            $pos = $this->sun->position((float) $photo->latitude, (float) $photo->longitude, $dt);

            $condition = $table->observed_condition ?? 'unknown';
            if (in_array($condition, ['sun', 'mixed'], true)) {
                $sunAzimuths[] = $pos['azimuth'];
            }
            if (in_array($condition, ['shade', 'mixed'], true)) {
                $shadeAzimuths[] = $pos['azimuth'];
            }
        }

        // If vision gave no conditions, infer from relative sun vs bearing (facing sun ≈ sunlit terrace)
        if ($sunAzimuths === [] && $shadeAzimuths === []) {
            foreach ($session->photos as $photo) {
                $bearings[] = (float) $photo->bearing;
                $dt = Carbon::parse(
                    $session->capture_date->format('Y-m-d').' '.$photo->captured_at,
                    $tz
                );
                $pos = $this->sun->position((float) $photo->latitude, (float) $photo->longitude, $dt);
                $delta = abs($this->angleDiff($pos['azimuth'], (float) $photo->bearing));
                if ($delta < 70 && $pos['elevation'] > 10) {
                    $sunAzimuths[] = $pos['azimuth'];
                } else {
                    $shadeAzimuths[] = $pos['azimuth'];
                }
            }
        }

        return [
            'sun_azimuths' => $sunAzimuths,
            'shade_azimuths' => $shadeAzimuths,
            'bearings' => $bearings,
            'has_umbrella_bias' => $hasUmbrellaBias,
        ];
    }

    /**
     * @param  array{sun_azimuths: array<int, float>, shade_azimuths: array<int, float>, bearings: array<int, float>, has_umbrella_bias: bool}  $profile
     */
    public function forecastDay(
        DetectedTable $table,
        Carbon $date,
        float $lat,
        float $lng,
        array $profile
    ): SunShadeForecast {
        $hourly = [];
        $sunHours = 0;
        $shadeHours = 0;

        $meanBearing = $profile['bearings']
            ? array_sum($profile['bearings']) / count($profile['bearings'])
            : 180.0;

        $sunCenter = $profile['sun_azimuths']
            ? $this->circularMean($profile['sun_azimuths'])
            : fmod($meanBearing + 0, 360);

        $halfWidth = 55.0;
        if ($profile['sun_azimuths'] && $profile['shade_azimuths']) {
            $halfWidth = 45.0;
        }

        for ($hour = 6; $hour <= 21; $hour++) {
            $dt = $date->copy()->setTime($hour, 0);
            $pos = $this->sun->position($lat, $lng, $dt);
            $elevation = $pos['elevation'];
            $azimuth = $pos['azimuth'];

            $inSunSector = abs($this->angleDiff($azimuth, $sunCenter)) <= $halfWidth;
            $isSun = $elevation > 5 && $inSunSector;
            $reason = $isSun ? 'Sonnenazimut im beobachteten Sonnenkorridor' : 'Außerhalb Sonnenkorridor oder unter Horizont';

            if ($table->has_umbrella || $profile['has_umbrella_bias']) {
                $radius = (float) ($table->umbrella_radius_m ?: 1.5);
                $height = (float) ($table->umbrella_height_m ?: 2.2);
                // Simplified: when sun is high, umbrella covers more; low sun casts long shadows past table
                $coverFactor = $elevation > 0 ? min(1.0, ($height / max(0.1, tan(deg2rad($elevation)))) / max(0.5, $radius * 2)) : 1.0;
                if ($isSun && $elevation > 25 && $coverFactor < 1.2) {
                    $isSun = false;
                    $reason = 'Schirm deckt Tisch bei hoher Sonne ab (Heuristik)';
                } elseif ($isSun && $elevation <= 25) {
                    $reason .= '; niedriger Sonnenstand – Schirmschatten versetzt';
                }
            }

            if ($elevation <= 5) {
                $isSun = false;
                $reason = 'Sonne unter / nahe Horizont';
            }

            if ($isSun) {
                $sunHours++;
            } else {
                $shadeHours++;
            }

            $hourly[] = [
                'hour' => $hour,
                'sun' => $isSun,
                'elevation' => $elevation,
                'azimuth' => $azimuth,
                'reason' => $reason,
            ];
        }

        return SunShadeForecast::query()->updateOrCreate(
            [
                'detected_table_id' => $table->id,
                'forecast_date' => $date->toDateString(),
            ],
            [
                'hourly' => $hourly,
                'sun_hours' => $sunHours,
                'shade_hours' => $shadeHours,
            ]
        );
    }

    /**
     * @param  array<int, float>  $angles
     */
    private function circularMean(array $angles): float
    {
        $sin = 0.0;
        $cos = 0.0;
        foreach ($angles as $a) {
            $sin += sin(deg2rad($a));
            $cos += cos(deg2rad($a));
        }
        $n = max(1, count($angles));
        $mean = rad2deg(atan2($sin / $n, $cos / $n));

        return fmod($mean + 360, 360);
    }

    private function angleDiff(float $a, float $b): float
    {
        $d = fmod($a - $b + 540, 360) - 180;

        return $d;
    }
}
