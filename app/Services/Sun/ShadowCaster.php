<?php

namespace App\Services\Sun;

/**
 * Local-meter ray casting against OSM buildings (polygons) and trees (discs).
 * Azimuth is clockwise from north, matching SunPositionService.
 */
class ShadowCaster
{
    public const TABLE_HEIGHT_M = 0.75;

    public const HORIZON_ELEVATION = 5.0;

    /**
     * @param  list<array{kind: string, name?: ?string, height_m: float, radius_m?: ?float, polygon: list<array{lat: float, lng: float}>}>  $occluders
     * @return array{sun: bool, reason: string, occluder: ?string}
     */
    public function isSunlit(
        float $lat,
        float $lng,
        float $azimuth,
        float $elevation,
        array $occluders,
        bool $hasUmbrella = false,
        float $umbrellaHeightM = 2.2,
        float $umbrellaRadiusM = 1.5,
    ): array {
        if ($elevation <= self::HORIZON_ELEVATION) {
            return ['sun' => false, 'reason' => 'Sonne unter / nahe Horizont', 'occluder' => null];
        }

        if ($hasUmbrella) {
            $coverFactor = min(1.0, ($umbrellaHeightM / max(0.1, tan(deg2rad($elevation)))) / max(0.5, $umbrellaRadiusM * 2));
            if ($elevation > 25 && $coverFactor < 1.2) {
                return ['sun' => false, 'reason' => 'Schirm deckt Tisch bei hoher Sonne ab', 'occluder' => 'umbrella'];
            }
        }

        $origin = [0.0, 0.0];
        $dir = [sin(deg2rad($azimuth)), cos(deg2rad($azimuth))];

        foreach ($occluders as $occluder) {
            $kind = $occluder['kind'] ?? 'building';
            $height = (float) ($occluder['height_m'] ?? 0);
            if ($height <= self::TABLE_HEIGHT_M) {
                continue;
            }

            if ($kind === 'tree') {
                $center = $occluder['polygon'][0] ?? null;
                if (! $center) {
                    continue;
                }
                $hit = $this->rayHitsDisc(
                    $origin,
                    $dir,
                    $this->toXy($center['lat'], $center['lng'], $lat, $lng),
                    (float) ($occluder['radius_m'] ?? 3.0),
                );
            } else {
                $ring = [];
                foreach ($occluder['polygon'] ?? [] as $pt) {
                    $ring[] = $this->toXy($pt['lat'], $pt['lng'], $lat, $lng);
                }
                if (count($ring) < 3) {
                    continue;
                }
                if ($this->pointInPolygon($origin, $ring)) {
                    continue;
                }
                $hit = $this->rayHitsPolygon($origin, $dir, $ring);
            }

            if ($hit === null) {
                continue;
            }

            $rayHeight = self::TABLE_HEIGHT_M + $hit * tan(deg2rad($elevation));
            if ($height > $rayHeight) {
                $label = $occluder['name'] ?: ($kind === 'tree' ? 'Baum' : 'Gebäude');

                return [
                    'sun' => false,
                    'reason' => sprintf('%s in %.0f m blockiert die Sonne (%.0f m hoch)', $label, $hit, $height),
                    'occluder' => $label,
                ];
            }
        }

        $reason = 'Freie Sicht zur Sonne';
        if ($hasUmbrella && $elevation <= 25) {
            $reason .= '; niedriger Sonnenstand – Schirmschatten versetzt';
        }

        return ['sun' => true, 'reason' => $reason, 'occluder' => null];
    }

    /**
     * @return array{0: float, 1: float} east, north meters
     */
    public function toXy(float $lat, float $lng, float $originLat, float $originLng): array
    {
        $x = ($lng - $originLng) * 111320.0 * cos(deg2rad($originLat));
        $y = ($lat - $originLat) * 110540.0;

        return [$x, $y];
    }

    /**
     * @param  array{0: float, 1: float}  $origin
     * @param  array{0: float, 1: float}  $dir
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public function rayHitsPolygon(array $origin, array $dir, array $ring): ?float
    {
        $best = null;
        $count = count($ring);
        for ($i = 0; $i < $count; $i++) {
            $a = $ring[$i];
            $b = $ring[($i + 1) % $count];
            $t = $this->rayHitsSegment($origin, $dir, $a, $b);
            if ($t !== null && ($best === null || $t < $best)) {
                $best = $t;
            }
        }

        return $best;
    }

    /**
     * Distance in meters along a unit direction, or null.
     *
     * @param  array{0: float, 1: float}  $origin
     * @param  array{0: float, 1: float}  $dir
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    public function rayHitsSegment(array $origin, array $dir, array $a, array $b): ?float
    {
        $rx = $dir[0];
        $ry = $dir[1];
        $sx = $b[0] - $a[0];
        $sy = $b[1] - $a[1];
        $rxs = $rx * $sy - $ry * $sx;
        if (abs($rxs) < 1e-10) {
            return null;
        }

        $qpx = $a[0] - $origin[0];
        $qpy = $a[1] - $origin[1];
        $t = ($qpx * $sy - $qpy * $sx) / $rxs;
        $u = ($qpx * $ry - $qpy * $rx) / $rxs;

        if ($t < 0.3 || $u < 0.0 || $u > 1.0) {
            return null;
        }

        return $t;
    }

    /**
     * @param  array{0: float, 1: float}  $origin
     * @param  array{0: float, 1: float}  $dir
     * @param  array{0: float, 1: float}  $center
     */
    public function rayHitsDisc(array $origin, array $dir, array $center, float $radius): ?float
    {
        $fx = $center[0] - $origin[0];
        $fy = $center[1] - $origin[1];
        $dist = hypot($fx, $fy);
        if ($dist <= $radius) {
            return 0.3;
        }

        $t = $fx * $dir[0] + $fy * $dir[1];
        if ($t < 0.3) {
            return null;
        }

        $closestX = $origin[0] + $t * $dir[0];
        $closestY = $origin[1] + $t * $dir[1];
        $perp = hypot($closestX - $center[0], $closestY - $center[1]);
        if ($perp > $radius) {
            return null;
        }

        return max(0.3, $t - sqrt(max(0.0, $radius * $radius - $perp * $perp)));
    }

    /**
     * @param  array{0: float, 1: float}  $point
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public function pointInPolygon(array $point, array $ring): bool
    {
        $inside = false;
        $count = count($ring);
        $j = $count - 1;
        for ($i = 0; $i < $count; $i++) {
            $xi = $ring[$i][0];
            $yi = $ring[$i][1];
            $xj = $ring[$j][0];
            $yj = $ring[$j][1];
            $intersect = (($yi > $point[1]) !== ($yj > $point[1]))
                && ($point[0] < ($xj - $xi) * ($point[1] - $yi) / (($yj - $yi) ?: 1e-12) + $xi);
            if ($intersect) {
                $inside = ! $inside;
            }
            $j = $i;
        }

        return $inside;
    }
}
