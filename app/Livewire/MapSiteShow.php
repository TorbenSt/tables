<?php

namespace App\Livewire;

use App\Models\MapSite;
use App\Models\MapSunShadeForecast;
use App\Models\MapTable;
use App\Services\Sun\MapSunPredictor;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class MapSiteShow extends Component
{
    public MapSite $site;

    public ?int $selectedTableId = null;

    public string $forecastDate = '';

    public function mount(MapSite $site): void
    {
        $this->site = $site->load(['tables.forecasts', 'occluders', 'venue']);
        $this->selectedTableId = $site->tables->first()?->id;
        $this->forecastDate = now()->format('Y-m-15');
    }

    public function selectTable(int $id): void
    {
        $this->selectedTableId = $id;
    }

    public function recompute(MapSunPredictor $predictor): void
    {
        $predictor->generateForSite($this->site->fresh(['tables', 'venue']));
        $this->site->refresh()->load(['tables.forecasts', 'occluders', 'venue']);
        $this->selectedTableId = $this->site->tables->first()?->id;
        session()->flash('status', 'Sonnenzeiten neu berechnet.');
    }

    public function render()
    {
        $this->site->loadMissing(['tables.forecasts', 'occluders', 'venue']);
        $tables = $this->site->tables;
        $table = $this->selectedTableId
            ? MapTable::query()->find($this->selectedTableId)
            : null;

        $dayForecast = null;
        $yearOverview = collect();

        if ($table) {
            $dayForecast = MapSunShadeForecast::query()
                ->where('map_table_id', $table->id)
                ->whereDate('forecast_date', $this->forecastDate)
                ->first();

            if (! $dayForecast) {
                $predictor = app(MapSunPredictor::class);
                $occluders = $this->site->occluders->map(fn ($o) => [
                    'kind' => $o->kind,
                    'name' => $o->name,
                    'height_m' => (float) $o->height_m,
                    'radius_m' => $o->radius_m !== null ? (float) $o->radius_m : null,
                    'polygon' => $o->polygon ?? [],
                ])->all();
                $dayForecast = $predictor->forecastDay(
                    $table,
                    Carbon::parse($this->forecastDate, $this->site->venue?->timezone ?? 'Europe/Berlin'),
                    $occluders,
                );
            }

            $yearOverview = MapSunShadeForecast::query()
                ->where('map_table_id', $table->id)
                ->orderBy('forecast_date')
                ->get()
                ->filter(fn ($f) => $f->forecast_date->day === 15);
        }

        return view('livewire.map-site-show', [
            'table' => $table,
            'tables' => $tables,
            'dayForecast' => $dayForecast,
            'yearOverview' => $yearOverview,
            'title' => $this->site->displayTitle(),
            'imagery' => config('services.map_imagery'),
            'mapTables' => $tables->map(fn (MapTable $t) => [
                'lat' => $t->latitude,
                'lng' => $t->longitude,
                'stable_key' => $t->stable_key,
                'color_hex' => $t->color_hex,
                'label' => $t->label,
                'has_umbrella' => $t->has_umbrella,
            ])->values()->all(),
        ]);
    }
}
