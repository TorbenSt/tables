<?php

namespace Tests\Unit;

use App\Services\Sun\ShadowCaster;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ShadowCasterTest extends TestCase
{
    private ShadowCaster $caster;

    private float $lat = 52.52;

    private float $lng = 13.405;

    protected function setUp(): void
    {
        parent::setUp();
        $this->caster = new ShadowCaster;
    }

    #[Test]
    public function east_building_blocks_morning_sun_but_not_afternoon(): void
    {
        $building = [
            'kind' => 'building',
            'name' => 'Haus Ost',
            'height_m' => 20.0,
            'polygon' => [
                $this->offset(8, -6),
                $this->offset(20, -6),
                $this->offset(20, 6),
                $this->offset(8, 6),
            ],
        ];

        $morning = $this->caster->isSunlit($this->lat, $this->lng, 90.0, 30.0, [$building]);
        $afternoon = $this->caster->isSunlit($this->lat, $this->lng, 270.0, 30.0, [$building]);
        $south = $this->caster->isSunlit($this->lat, $this->lng, 180.0, 45.0, [$building]);

        $this->assertFalse($morning['sun']);
        $this->assertStringContainsString('Haus Ost', $morning['reason']);
        $this->assertTrue($afternoon['sun']);
        $this->assertTrue($south['sun']);
    }

    #[Test]
    public function building_that_contains_the_table_is_ignored(): void
    {
        $self = [
            'kind' => 'building',
            'name' => 'Café selbst',
            'height_m' => 12.0,
            'polygon' => [
                $this->offset(-5, -5),
                $this->offset(5, -5),
                $this->offset(5, 5),
                $this->offset(-5, 5),
            ],
        ];

        $result = $this->caster->isSunlit($this->lat, $this->lng, 90.0, 40.0, [$self]);

        $this->assertTrue($result['sun']);
    }

    #[Test]
    public function umbrella_shades_at_high_sun(): void
    {
        $high = $this->caster->isSunlit($this->lat, $this->lng, 180.0, 50.0, [], true, 2.2, 1.5);
        $low = $this->caster->isSunlit($this->lat, $this->lng, 180.0, 15.0, [], true, 2.2, 1.5);

        $this->assertFalse($high['sun']);
        $this->assertTrue($low['sun']);
    }

    #[Test]
    public function sun_below_horizon_is_shade(): void
    {
        $result = $this->caster->isSunlit($this->lat, $this->lng, 90.0, 3.0, []);

        $this->assertFalse($result['sun']);
        $this->assertStringContainsString('Horizont', $result['reason']);
    }

    /**
     * @return array{lat: float, lng: float}
     */
    private function offset(float $eastM, float $northM): array
    {
        return [
            'lat' => $this->lat + $northM / 110540.0,
            'lng' => $this->lng + $eastM / (111320.0 * cos(deg2rad($this->lat))),
        ];
    }
}
