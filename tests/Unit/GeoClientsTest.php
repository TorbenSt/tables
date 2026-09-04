<?php

namespace Tests\Unit;

use App\Services\Geo\NominatimGeocoder;
use App\Services\Geo\OverpassClient;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GeoClientsTest extends TestCase
{
    #[Test]
    public function geocoder_parses_lat_lng_without_http(): void
    {
        Http::fake();

        $results = (new NominatimGeocoder)->search('52.520008, 13.404954');

        $this->assertCount(1, $results);
        $this->assertSame(52.520008, $results[0]['lat']);
        $this->assertSame(13.404954, $results[0]['lng']);
        $this->assertSame('coordinates', $results[0]['type']);
        Http::assertNothingSent();
    }

    #[Test]
    public function geocoder_maps_nominatim_hits(): void
    {
        Http::fake([
            'nominatim.openstreetmap.org/*' => Http::response([
                [
                    'lat' => '52.52',
                    'lon' => '13.40',
                    'display_name' => 'Brandenburger Tor, Berlin',
                    'type' => 'attraction',
                ],
            ], 200),
        ]);

        $results = (new NominatimGeocoder)->search('Brandenburger Tor');

        $this->assertSame('Brandenburger Tor, Berlin', $results[0]['display_name']);
        $this->assertEqualsWithDelta(52.52, $results[0]['lat'], 0.001);
        $this->assertEqualsWithDelta(13.40, $results[0]['lng'], 0.001);
    }

    #[Test]
    public function overpass_maps_buildings_and_trees(): void
    {
        Http::fake([
            'overpass-api.de/*' => Http::response([
                'elements' => [
                    [
                        'type' => 'way',
                        'id' => 11,
                        'tags' => ['building' => 'yes', 'building:levels' => '4', 'name' => 'Nachbarhaus'],
                        'geometry' => [
                            ['lat' => 52.5201, 'lon' => 13.4050],
                            ['lat' => 52.5202, 'lon' => 13.4050],
                            ['lat' => 52.5202, 'lon' => 13.4052],
                            ['lat' => 52.5201, 'lon' => 13.4052],
                            ['lat' => 52.5201, 'lon' => 13.4050],
                        ],
                    ],
                    [
                        'type' => 'node',
                        'id' => 22,
                        'lat' => 52.5203,
                        'lon' => 13.4051,
                        'tags' => ['natural' => 'tree', 'height' => '12'],
                    ],
                ],
            ], 200),
        ]);

        $occluders = (new OverpassClient)->occludersNear(52.52, 13.405);

        $this->assertCount(2, $occluders);
        $this->assertSame('building', $occluders[0]['kind']);
        $this->assertSame(12.0, $occluders[0]['height_m']);
        $this->assertCount(4, $occluders[0]['polygon']);
        $this->assertSame('tree', $occluders[1]['kind']);
        $this->assertSame(12.0, $occluders[1]['height_m']);
    }
}
