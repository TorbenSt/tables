<?php

namespace App\Services\Sun;

/**
 * Reads GPS, capture time and image direction from JPEG EXIF when present.
 * Many phones omit GPSImgDirection; GPS is more commonly available.
 */
class ExifMetaExtractor
{
    /**
     * @return array{latitude?: float, longitude?: float, time?: string, date?: string, bearing?: float}
     */
    public function fromPath(string $path): array
    {
        if (! function_exists('exif_read_data') || ! is_readable($path)) {
            return [];
        }

        $exif = @exif_read_data($path, null, true, false);
        if (! is_array($exif)) {
            return [];
        }

        $out = [];

        $lat = $this->gpsToDecimal($exif['GPS']['GPSLatitude'] ?? null, $exif['GPS']['GPSLatitudeRef'] ?? 'N');
        $lng = $this->gpsToDecimal($exif['GPS']['GPSLongitude'] ?? null, $exif['GPS']['GPSLongitudeRef'] ?? 'E');
        if ($lat !== null && $lng !== null) {
            $out['latitude'] = $lat;
            $out['longitude'] = $lng;
        }

        $datetime = $exif['EXIF']['DateTimeOriginal'] ?? $exif['IFD0']['DateTime'] ?? null;
        if (is_string($datetime) && preg_match('/^(\d{4}):(\d{2}):(\d{2}) (\d{2}):(\d{2}):(\d{2})$/', $datetime, $m)) {
            $out['date'] = "{$m[1]}-{$m[2]}-{$m[3]}";
            $out['time'] = "{$m[4]}:{$m[5]}";
        }

        $direction = $exif['GPS']['GPSImgDirection'] ?? $exif['GPS']['GPSDestBearing'] ?? null;
        $bearing = $this->fractionToFloat($direction);
        if ($bearing !== null) {
            $out['bearing'] = fmod($bearing + 360, 360);
        }

        return $out;
    }

    private function gpsToDecimal(mixed $coords, mixed $ref): ?float
    {
        if (! is_array($coords) || count($coords) < 3) {
            return null;
        }

        $deg = $this->fractionToFloat($coords[0]);
        $min = $this->fractionToFloat($coords[1]);
        $sec = $this->fractionToFloat($coords[2]);
        if ($deg === null || $min === null || $sec === null) {
            return null;
        }

        $decimal = $deg + ($min / 60) + ($sec / 3600);
        $ref = strtoupper((string) $ref);
        if (in_array($ref, ['S', 'W'], true)) {
            $decimal *= -1;
        }

        return round($decimal, 7);
    }

    private function fractionToFloat(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }
        if (! is_string($value)) {
            return null;
        }
        if (str_contains($value, '/')) {
            [$n, $d] = array_pad(explode('/', $value, 2), 2, '1');
            $denom = (float) $d;

            return $denom == 0.0 ? null : ((float) $n) / $denom;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
