<?php

namespace App\Services\Sun;

/**
 * Approximate solar position (azimuth from north clockwise, elevation degrees).
 * Based on NOAA / PSA simplified algorithm – sufficient for PoC shade heuristics.
 */
class SunPositionService
{
    /**
     * @return array{azimuth: float, elevation: float}
     */
    public function position(float $lat, float $lng, \DateTimeInterface $dt): array
    {
        $timestamp = $dt->getTimestamp();
        $tzOffset = (int) $dt->format('Z') / 3600;

        $julianDay = ($timestamp / 86400.0) + 2440587.5;
        $julianCentury = ($julianDay - 2451545.0) / 36525.0;

        $geomMeanLong = fmod(280.46646 + $julianCentury * (36000.76983 + $julianCentury * 0.0003032), 360);
        $geomMeanAnom = 357.52911 + $julianCentury * (35999.05029 - 0.0001537 * $julianCentury);
        $eccent = 0.016708634 - $julianCentury * (0.000042037 + 0.0000001267 * $julianCentury);

        $sunEqOfCtr = sin(deg2rad($geomMeanAnom)) * (1.914602 - $julianCentury * (0.004817 + 0.000014 * $julianCentury))
            + sin(deg2rad(2 * $geomMeanAnom)) * (0.019993 - 0.000101 * $julianCentury)
            + sin(deg2rad(3 * $geomMeanAnom)) * 0.000289;

        $sunTrueLong = $geomMeanLong + $sunEqOfCtr;
        $sunAppLong = $sunTrueLong - 0.00569 - 0.00478 * sin(deg2rad(125.04 - 1934.136 * $julianCentury));
        $meanObliq = 23 + (26 + ((21.448 - $julianCentury * (46.815 + $julianCentury * (0.00059 - $julianCentury * 0.001813)))) / 60) / 60;
        $obliqCorr = $meanObliq + 0.00256 * cos(deg2rad(125.04 - 1934.136 * $julianCentury));

        $sunDeclin = rad2deg(asin(sin(deg2rad($obliqCorr)) * sin(deg2rad($sunAppLong))));

        $varY = tan(deg2rad($obliqCorr / 2)) ** 2;
        $eqOfTime = 4 * rad2deg(
            $varY * sin(2 * deg2rad($geomMeanLong))
            - 2 * $eccent * sin(deg2rad($geomMeanAnom))
            + 4 * $eccent * $varY * sin(deg2rad($geomMeanAnom)) * cos(2 * deg2rad($geomMeanLong))
            - 0.5 * $varY * $varY * sin(4 * deg2rad($geomMeanLong))
            - 1.25 * $eccent * $eccent * sin(2 * deg2rad($geomMeanAnom))
        );

        $minutes = ((int) $dt->format('G')) * 60 + (int) $dt->format('i') + ((int) $dt->format('s')) / 60;
        $trueSolarTime = fmod($minutes + $eqOfTime + 4 * $lng - 60 * $tzOffset + 1440, 1440);
        $hourAngle = $trueSolarTime / 4 < 0 ? $trueSolarTime / 4 + 180 : $trueSolarTime / 4 - 180;

        $latRad = deg2rad($lat);
        $haRad = deg2rad($hourAngle);
        $zenith = rad2deg(acos(
            sin($latRad) * sin(deg2rad($sunDeclin))
            + cos($latRad) * cos(deg2rad($sunDeclin)) * cos($haRad)
        ));

        $azDenom = cos($latRad) * sin(deg2rad($zenith));
        if (abs($azDenom) > 0.001) {
            $azRad = ((sin($latRad) * cos(deg2rad($zenith))) - sin(deg2rad($sunDeclin))) / $azDenom;
            $azimuth = 180 - rad2deg(acos(max(-1, min(1, $azRad))));
            if ($hourAngle > 0) {
                $azimuth = fmod($azimuth + 180, 360);
            } else {
                $azimuth = fmod(540 - $azimuth, 360);
            }
        } else {
            $azimuth = $lat > 0 ? 180.0 : 0.0;
        }

        $elevation = 90.0 - $zenith;

        return [
            'azimuth' => round($azimuth, 2),
            'elevation' => round($elevation, 2),
        ];
    }
}
