<?php

namespace Tests\Feature;

use App\Models\PhotoSession;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PhotoSessionFormTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Venue
    {
        return Venue::query()->create([
            'name' => 'Testcafé',
            'latitude' => 52.52,
            'longitude' => 13.4,
            'tables_indoor' => 10,
            'tables_outdoor_sun' => 5,
            'tables_outdoor_shade' => 5,
            'timezone' => 'Europe/Berlin',
        ]);
    }

    #[Test]
    public function create_page_lets_user_choose_gallery_or_camera(): void
    {
        $this->venue();

        $response = $this->get(route('photo-sessions.create'));

        $response->assertOk();
        $response->assertSee('Galerie hochladen');
        $response->assertSee('Mit Handy aufnehmen');
        $response->assertSee(route('photo-sessions.gallery'), false);
        $response->assertSee(route('photo-sessions.camera'), false);
    }

    #[Test]
    public function gallery_form_uses_shared_viewpoint(): void
    {
        $this->venue();

        $response = $this->get(route('photo-sessions.gallery'));

        $response->assertOk();
        $response->assertSee('Gemeinsamer Standpunkt');
        $response->assertSee('Gleicher Standort und gleiche Blickrichtung für alle Fotos');
        $response->assertSee('Standort und Blickrichtung je Foto anpassen');
        $response->assertSee('Zeiger auf die Richtung ziehen');
        $response->assertDontSee('Handy-Kamera');
        $response->assertDontSee('tables-camera-overlay');
    }

    #[Test]
    public function camera_flow_starts_with_lock_step(): void
    {
        $this->venue();

        $response = $this->get(route('photo-sessions.camera'));

        $response->assertOk();
        $response->assertSee('Standpunkt merken');
        $response->assertSee('GPS &amp; Kompass jetzt lesen', false);
        $response->assertSee('tables-camera-overlay');
        $response->assertDontSee('Fotos über den Tag');
    }

    #[Test]
    public function draft_sessions_continue_on_camera_page(): void
    {
        $venue = $this->venue();
        $session = PhotoSession::query()->create([
            'venue_id' => $venue->id,
            'title' => 'Entwurf',
            'capture_date' => now()->toDateString(),
            'status' => 'draft',
            'viewpoint_latitude' => 52.52,
            'viewpoint_longitude' => 13.4,
            'viewpoint_bearing' => 90,
        ]);

        $this->get(route('photo-sessions.show', $session))
            ->assertRedirect(route('photo-sessions.camera.continue', $session));

        $this->get(route('photo-sessions.camera.continue', $session))
            ->assertOk()
            ->assertSee('Fotos über den Tag');
    }
}
