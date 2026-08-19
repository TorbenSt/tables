<?php

namespace Tests\Feature;

use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoSessionFormTest extends TestCase
{
    use RefreshDatabase;
    #[Test]
    public function create_page_offers_camera_capture(): void
    {
        Venue::query()->create([
            'name' => 'Testcafé',
            'latitude' => 52.52,
            'longitude' => 13.4,
            'tables_indoor' => 10,
            'tables_outdoor_sun' => 5,
            'tables_outdoor_shade' => 5,
            'timezone' => 'Europe/Berlin',
        ]);

        $response = $this->get(route('photo-sessions.create'));

        $response->assertOk();
        $response->assertSee('Handy-Kamera');
        $response->assertSee('tables-camera-overlay');
        $response->assertSee('data-open-camera', false);
        $response->assertSee('Blickrichtung');
        $response->assertSee('Zeiger auf die Richtung ziehen');
        $response->assertDontSee('Blickrichtung (° von Nord)');
    }
}
