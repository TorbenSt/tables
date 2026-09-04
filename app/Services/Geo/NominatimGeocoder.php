<?php

namespace App\Services\Geo;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class NominatimGeocoder
{
    /**
     * @return list<array{lat: float, lng: float, display_name: string, type: string}>
     */
    public function search(string $query, int $limit = 5): array
    {
        $query = trim($query);
        if ($query === '') {
            return [];
        }

        if ($coords = $this->parseCoordinates($query)) {
            return [$coords];
        }

        $base = rtrim((string) config('services.nominatim.base_url'), '/');
        $response = Http::timeout(15)
            ->withHeaders([
                'User-Agent' => (string) config('services.nominatim.user_agent'),
                'Accept-Language' => 'de',
            ])
            ->get($base.'/search', [
                'q' => $query,
                'format' => 'jsonv2',
                'limit' => $limit,
                'addressdetails' => 0,
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('Geocoding fehlgeschlagen: '.$response->status());
        }

        $results = [];
        foreach ($response->json() ?? [] as $row) {
            if (! isset($row['lat'], $row['lon'])) {
                continue;
            }
            $results[] = [
                'lat' => (float) $row['lat'],
                'lng' => (float) $row['lon'],
                'display_name' => (string) ($row['display_name'] ?? $query),
                'type' => (string) ($row['type'] ?? 'place'),
            ];
        }

        return $results;
    }

    /**
     * @return array{lat: float, lng: float, display_name: string, type: string}|null
     */
    public function parseCoordinates(string $query): ?array
    {
        if (! preg_match('/^\s*(-?\d+(?:\.\d+)?)\s*[,;\s]\s*(-?\d+(?:\.\d+)?)\s*$/', $query, $m)) {
            return null;
        }

        $lat = (float) $m[1];
        $lng = (float) $m[2];
        if (abs($lat) > 90 || abs($lng) > 180) {
            return null;
        }

        return [
            'lat' => $lat,
            'lng' => $lng,
            'display_name' => sprintf('%s, %s', $lat, $lng),
            'type' => 'coordinates',
        ];
    }
}
