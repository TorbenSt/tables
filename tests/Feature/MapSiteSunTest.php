<?php

namespace Tests\Feature;

use App\Livewire\MapSiteEditor;
use App\Models\MapSite;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MapSiteSunTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Venue
    {
        return Venue::query()->create([
            'name' => 'Testcafé',
            'latitude' => 52.52,
            'longitude' => 13.405,
            'tables_indoor' => 10,
            'tables_outdoor_sun' => 5,
            'tables_outdoor_shade' => 5,
            'timezone' => 'Europe/Berlin',
        ]);
    }

    #[Test]
    public function dashboard_and_nav_point_to_map_flow(): void
    {
        $this->venue();

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Tische markieren')
            ->assertSee(route('map-sites.create'), false)
            ->assertSee('Alter Foto-Flow');
    }

    #[Test]
    public function editor_accepts_coordinates_and_table_clicks(): void
    {
        $this->venue();

        Livewire::test(MapSiteEditor::class)
            ->set('query', '52.5201, 13.4051')
            ->call('search')
            ->assertSet('latitude', 52.5201)
            ->assertSet('longitude', 13.4051)
            ->call('addTable', 52.5202, 13.4052)
            ->assertCount('tables', 1)
            ->assertSee('T1');
    }

    #[Test]
    public function compute_stores_site_tables_and_forecasts(): void
    {
        $this->venue();
        Http::fake([
            'overpass-api.de/*' => Http::response(['elements' => []], 200),
        ]);

        Livewire::test(MapSiteEditor::class)
            ->set('query', '52.52, 13.405')
            ->call('search')
            ->set('title', 'Terrasse Test')
            ->call('addTable', 52.52005, 13.40505)
            ->call('toggleUmbrella', 0)
            ->call('generateForecast')
            ->assertRedirect();

        $site = MapSite::query()->with(['tables.forecasts', 'occluders'])->first();
        $this->assertNotNull($site);
        $this->assertSame('ready', $site->status);
        $this->assertSame('Terrasse Test', $site->title);
        $this->assertCount(1, $site->tables);
        $this->assertTrue($site->tables->first()->has_umbrella);
        $this->assertGreaterThanOrEqual(12, $site->tables->first()->forecasts->count());
        $this->assertCount(16, $site->tables->first()->forecasts->first()->hourly);

        $this->get(route('map-sites.show', $site))
            ->assertOk()
            ->assertSee('Sonn / Schatten Prognose')
            ->assertSee('T1');
    }

    #[Test]
    public function compute_requires_at_least_one_table(): void
    {
        $this->venue();

        Livewire::test(MapSiteEditor::class)
            ->set('query', '52.52, 13.405')
            ->call('search')
            ->call('generateForecast')
            ->assertHasErrors(['tables']);
    }
}
