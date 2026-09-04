<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class OverpassClient
{
    /**
     * Fetch nearby buildings (polygons) and trees around a point.
     *
     * @return list<array{kind: string, osm_id: string, name: ?string, height_m: float, radius_m: ?float, polygon: list<array{lat: float, lng: float}>}>
     */
    public function occludersNear(float $lat, float $lng, ?int $radiusM = null): array
    {
        $radius = $radiusM ?? (int) config('services.overpass.radius_m', 80);
        $timeout = (int) config('services.overpass.timeout', 18);
        $endpoints = array_values(array_unique(array_filter([
            (string) config('services.overpass.url'),
            ...(array) config('services.overpass.fallbacks', []),
        ])));

        $ql = sprintf(
            '[out:json][timeout:%d];(way["building"](around:%d,%s,%s);node["natural"="tree"](around:%d,%s,%s););out geom;',
            max(8, $timeout - 3),
            $radius,
            $this->coord($lat),
            $this->coord($lng),
            $radius,
            $this->coord($lat),
            $this->coord($lng),
        );

        $lastError = 'kein Overpass-Endpunkt konfiguriert';
        $payload = null;
        foreach ($endpoints as $url) {
            try {
                $response = Http::timeout($timeout)
                    ->withHeaders([
                        'User-Agent' => (string) config('services.nominatim.user_agent'),
                    ])
                    ->asForm()
                    ->post($url, ['data' => $ql]);
            } catch (Throwable $e) {
                $lastError = $e->getMessage();

                continue;
            }

            if (! $response->successful()) {
                $lastError = 'HTTP '.$response->status();

                continue;
            }

            $payload = $response->json();
            break;
        }

        if ($payload === null) {
            throw new RuntimeException('Overpass-Fehler: '.$lastError);
        }

        $elements = $payload['elements'] ?? [];
        $occluders = [];

        foreach ($elements as $el) {
            $type = $el['type'] ?? '';
            $tags = $el['tags'] ?? [];

            if ($type === 'way' && isset($tags['building'])) {
                $polygon = $this->geometryToPolygon($el['geometry'] ?? []);
                if (count($polygon) < 3) {
                    continue;
                }
                $occluders[] = [
                    'kind' => 'building',
                    'osm_id' => 'way/'.($el['id'] ?? 0),
                    'name' => $tags['name'] ?? null,
                    'height_m' => $this->buildingHeight($tags),
                    'radius_m' => null,
                    'polygon' => $polygon,
                ];
            }

            if ($type === 'node' && ($tags['natural'] ?? '') === 'tree') {
                $occluders[] = [
                    'kind' => 'tree',
                    'osm_id' => 'node/'.($el['id'] ?? 0),
                    'name' => $tags['name'] ?? null,
                    'height_m' => $this->treeHeight($tags),
                    'radius_m' => $this->treeRadius($tags),
                    'polygon' => [[
                        'lat' => (float) ($el['lat'] ?? 0),
                        'lng' => (float) ($el['lon'] ?? 0),
                    ]],
                ];
            }
        }

        return $occluders;
    }

    /**
     * @param  list<array{lat?: mixed, lon?: mixed}>  $geometry
     * @return list<array{lat: float, lng: float}>
     */
    private function geometryToPolygon(array $geometry): array
    {
        $points = [];
        foreach ($geometry as $pt) {
            if (! isset($pt['lat'], $pt['lon'])) {
                continue;
            }
            $points[] = [
                'lat' => (float) $pt['lat'],
                'lng' => (float) $pt['lon'],
            ];
        }

        if ($points !== [] && $this->samePoint($points[0], $points[count($points) - 1])) {
            array_pop($points);
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function buildingHeight(array $tags): float
    {
        if (isset($tags['height']) && is_numeric($tags['height'])) {
            return max(2.0, (float) $tags['height']);
        }
        if (isset($tags['building:levels']) && is_numeric($tags['building:levels'])) {
            return max(3.0, (float) $tags['building:levels'] * 3.0);
        }

        return 9.0;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function treeHeight(array $tags): float
    {
        if (isset($tags['height']) && is_numeric($tags['height'])) {
            return max(2.0, (float) $tags['height']);
        }

        return 10.0;
    }

    /**
     * @param  array<string, mixed>  $tags
     */
    private function treeRadius(array $tags): float
    {
        if (isset($tags['diameter_crown']) && is_numeric($tags['diameter_crown'])) {
            return max(1.0, (float) $tags['diameter_crown'] / 2);
        }

        return 3.0;
    }

    /**
     * @param  array{lat: float, lng: float}  $a
     * @param  array{lat: float, lng: float}  $b
     */
    private function samePoint(array $a, array $b): bool
    {
        return abs($a['lat'] - $b['lat']) < 1e-8 && abs($a['lng'] - $b['lng']) < 1e-8;
    }

    private function coord(float $value): string
    {
        return number_format($value, 7, '.', '');
    }
}
