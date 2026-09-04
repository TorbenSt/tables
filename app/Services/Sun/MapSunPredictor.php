<?php

namespace App\Services\Sun;

use App\Models\MapOccluder;
use App\Models\MapSite;
use App\Models\MapSunShadeForecast;
use App\Models\MapTable;
use App\Services\Geo\OverpassClient;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Throwable;

class MapSunPredictor
{
    public function __construct(
        private SunPositionService $sun,
        private ShadowCaster $caster,
        private OverpassClient $overpass,
    ) {}

    /**
     * @return Collection<int, MapSunShadeForecast>
     */
    public function generateForSite(MapSite $site, bool $refreshOccluders = true): Collection
    {
        $site->load(['tables', 'venue']);
        $tz = $site->venue?->timezone ?? 'Europe/Berlin';
        $warning = null;

        if ($refreshOccluders) {
            try {
                $this->refreshOccluders($site);
            } catch (Throwable $e) {
                $warning = 'OSM-Gebäude nicht geladen: '.$e->getMessage();
                if ($site->occluders()->count() === 0) {
                    $site->occluders()->delete();
                }
            }
        }

        $site->load('occluders');
        $occluders = $this->occludersAsArray($site);

        $created = collect();
        foreach ($site->tables as $table) {
            $table->forecasts()->delete();
            for ($month = 1; $month <= 12; $month++) {
                $date = Carbon::create(null, $month, 15, 12, 0, 0, $tz);
                $created->push($this->forecastDay($table, $date, $occluders));
            }
            $today = Carbon::now($tz)->startOfDay();
            if ($today->day !== 15) {
                $created->push($this->forecastDay($table, $today, $occluders));
            }
        }

        $site->status = 'ready';
        $site->error_message = $warning;
        $site->save();

        return $created;
    }

    public function refreshOccluders(MapSite $site): void
    {
        $fetched = $this->overpass->occludersNear((float) $site->latitude, (float) $site->longitude);
        $site->occluders()->where('source', 'osm')->delete();

        foreach ($fetched as $row) {
            MapOccluder::query()->create([
                'map_site_id' => $site->id,
                'kind' => $row['kind'],
                'source' => 'osm',
                'osm_id' => $row['osm_id'],
                'name' => $row['name'],
                'height_m' => $row['height_m'],
                'radius_m' => $row['radius_m'],
                'polygon' => $row['polygon'],
            ]);
        }
    }

    /**
     * @param  list<array{kind: string, name?: ?string, height_m: float, radius_m?: ?float, polygon: list<array{lat: float, lng: float}>}>  $occluders
     */
    public function forecastDay(MapTable $table, Carbon $date, array $occluders): MapSunShadeForecast
    {
        $hourly = [];
        $sunHours = 0;
        $shadeHours = 0;

        for ($hour = 6; $hour <= 21; $hour++) {
            $dt = $date->copy()->setTime($hour, 0);
            $pos = $this->sun->position((float) $table->latitude, (float) $table->longitude, $dt);
            $hit = $this->caster->isSunlit(
                (float) $table->latitude,
                (float) $table->longitude,
                $pos['azimuth'],
                $pos['elevation'],
                $occluders,
                (bool) $table->has_umbrella,
                (float) ($table->umbrella_height_m ?: 2.2),
                (float) ($table->umbrella_radius_m ?: 1.5),
            );

            if ($hit['sun']) {
                $sunHours++;
            } else {
                $shadeHours++;
            }

            $hourly[] = [
                'hour' => $hour,
                'sun' => $hit['sun'],
                'sun_pct' => $hit['sun'] ? 100 : 0,
                'shade_pct' => $hit['sun'] ? 0 : 100,
                'elevation' => $pos['elevation'],
                'azimuth' => $pos['azimuth'],
                'reason' => $hit['reason'],
            ];
        }

        return MapSunShadeForecast::query()->updateOrCreate(
            [
                'map_table_id' => $table->id,
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
     * @return list<array{kind: string, name: ?string, height_m: float, radius_m: ?float, polygon: list<array{lat: float, lng: float}>}>
     */
    private function occludersAsArray(MapSite $site): array
    {
        return $site->occluders->map(fn (MapOccluder $o) => [
            'kind' => $o->kind,
            'name' => $o->name,
            'height_m' => (float) $o->height_m,
            'radius_m' => $o->radius_m !== null ? (float) $o->radius_m : null,
            'polygon' => $o->polygon ?? [],
        ])->all();
    }
}
