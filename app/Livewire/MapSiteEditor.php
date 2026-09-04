<?php

namespace App\Livewire;

use App\Models\MapSite;
use App\Models\MapTable;
use App\Models\Venue;
use App\Services\Geo\NominatimGeocoder;
use App\Services\Sun\MapSunPredictor;
use App\Support\TableColorPalette;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Throwable;

#[Layout('components.layouts.app')]
class MapSiteEditor extends Component
{
    public string $query = '';

    public string $title = '';

    public ?int $venue_id = null;

    public ?float $latitude = null;

    public ?float $longitude = null;

    public int $zoom = 19;

    /** @var list<array{lat: float, lng: float, display_name: string, type: string}> */
    public array $geoResults = [];

    public ?string $searchError = null;

    /** @var list<array{lat: float, lng: float, stable_key: string, color_hex: string, label: string, has_umbrella: bool}> */
    public array $tables = [];

    public function mount(): void
    {
        $venue = Venue::query()->first();
        $this->venue_id = $venue?->id;
        $this->latitude = $venue?->latitude ?? 52.520008;
        $this->longitude = $venue?->longitude ?? 13.404954;
        $this->title = $venue?->name ? $venue->name.' — Terrasse' : '';
        $this->query = $venue ? sprintf('%s, %s', $venue->latitude, $venue->longitude) : '';
    }

    public function search(NominatimGeocoder $geocoder): void
    {
        $this->searchError = null;
        $this->geoResults = [];

        try {
            $results = $geocoder->search($this->query);
        } catch (Throwable $e) {
            $this->searchError = $e->getMessage();

            return;
        }

        if ($results === []) {
            $this->searchError = 'Kein Treffer. Adresse oder „lat, lng“ versuchen.';

            return;
        }

        if (count($results) === 1) {
            $this->applyResult($results[0]);

            return;
        }

        $this->geoResults = $results;
    }

    public function pickResult(int $index): void
    {
        if (! isset($this->geoResults[$index])) {
            return;
        }
        $this->applyResult($this->geoResults[$index]);
        $this->geoResults = [];
    }

    public function addTable(float $lat, float $lng): void
    {
        foreach ($this->tables as $table) {
            if ($this->distanceM($lat, $lng, $table['lat'], $table['lng']) < 1.5) {
                return;
            }
        }

        $n = count($this->tables) + 1;
        $key = 'T'.$n;
        $this->tables[] = [
            'lat' => round($lat, 7),
            'lng' => round($lng, 7),
            'stable_key' => $key,
            'color_hex' => TableColorPalette::forKey($key),
            'label' => 'Außentisch '.$n,
            'has_umbrella' => false,
        ];
        $this->dispatch('map-tables-sync', tables: $this->tables);
    }

    public function removeTable(int $index): void
    {
        if (! isset($this->tables[$index])) {
            return;
        }
        unset($this->tables[$index]);
        $this->tables = array_values($this->tables);
        $this->dispatch('map-tables-sync', tables: $this->tables);
    }

    public function toggleUmbrella(int $index): void
    {
        if (! isset($this->tables[$index])) {
            return;
        }
        $this->tables[$index]['has_umbrella'] = ! $this->tables[$index]['has_umbrella'];
        $this->dispatch('map-tables-sync', tables: $this->tables);
    }

    public function generateForecast(MapSunPredictor $predictor)
    {
        $this->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'tables' => 'required|array|min:1',
        ], [
            'tables.min' => 'Mindestens einen Außentisch auf der Karte markieren.',
        ]);

        $site = MapSite::query()->create([
            'venue_id' => $this->venue_id,
            'title' => $this->title !== '' ? $this->title : null,
            'address' => $this->query !== '' ? $this->query : null,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'zoom' => $this->zoom,
            'imagery_source' => 'esri',
            'status' => 'processing',
        ]);

        foreach ($this->tables as $i => $row) {
            MapTable::query()->create([
                'map_site_id' => $site->id,
                'stable_key' => $row['stable_key'],
                'color_hex' => $row['color_hex'],
                'label' => $row['label'],
                'latitude' => $row['lat'],
                'longitude' => $row['lng'],
                'has_umbrella' => $row['has_umbrella'],
                'umbrella_height_m' => $row['has_umbrella'] ? 2.2 : null,
                'umbrella_radius_m' => $row['has_umbrella'] ? 1.5 : null,
                'sort_order' => $i,
            ]);
        }

        $predictor->generateForSite($site->fresh(['tables', 'venue']));

        session()->flash('status', 'Sonnenzeiten aus Gebäudeumgebung berechnet.');

        return $this->redirect(route('map-sites.show', $site), navigate: true);
    }

    public function render()
    {
        return view('livewire.map-site-editor', [
            'title' => 'Tische auf Karte',
            'imagery' => config('services.map_imagery'),
        ]);
    }

    /**
     * @param  array{lat: float, lng: float, display_name: string, type: string}  $result
     */
    private function applyResult(array $result): void
    {
        $this->latitude = $result['lat'];
        $this->longitude = $result['lng'];
        $this->query = $result['display_name'];
        if ($this->title === '') {
            $this->title = $result['display_name'];
        }
        $this->zoom = 19;
        $this->dispatch('map-fly', lat: $this->latitude, lng: $this->longitude, zoom: $this->zoom);
    }

    private function distanceM(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $dx = ($lng1 - $lng2) * 111320.0 * cos(deg2rad(($lat1 + $lat2) / 2));
        $dy = ($lat1 - $lat2) * 110540.0;

        return hypot($dx, $dy);
    }
}
