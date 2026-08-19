<?php

namespace App\Livewire;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\SunShadeForecast;
use App\Services\Sun\TablePhotoAnalyzer;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class PhotoSessionShow extends Component
{
    public PhotoSession $session;

    public ?int $selectedTableId = null;

    public string $forecastDate = '';

    public function mount(PhotoSession $session): void
    {
        $this->session = $session->load(['photos', 'detectedTables.photo', 'venue']);
        $this->selectedTableId = $session->detectedTables->first()?->id;
        $this->forecastDate = now()->format('Y-m-15');
    }

    public function selectTable(int $id): void
    {
        $this->selectedTableId = $id;
    }

    public function reanalyze(TablePhotoAnalyzer $analyzer): void
    {
        $analyzer->analyze($this->session);
        $this->session->refresh()->load(['photos', 'detectedTables.photo', 'venue']);
        $this->selectedTableId = $this->session->detectedTables->first()?->id;
        session()->flash('status', 'Analyse erneut ausgeführt.');
    }

    public function render()
    {
        $table = $this->selectedTableId
            ? DetectedTable::query()->with('photo')->find($this->selectedTableId)
            : null;

        $dayForecast = null;
        $yearOverview = collect();

        if ($table) {
            $dayForecast = SunShadeForecast::query()
                ->where('detected_table_id', $table->id)
                ->whereDate('forecast_date', $this->forecastDate)
                ->first();

            // If exact date missing, compute on the fly for the chosen date
            if (! $dayForecast && $this->session->photos->isNotEmpty()) {
                $predictor = app(\App\Services\Sun\SunShadePredictor::class);
                $profile = $predictor->buildExposureProfile($this->session->load(['photos', 'detectedTables.photo']));
                $photo = $this->session->photos->first();
                $dayForecast = $predictor->forecastDay(
                    $table,
                    Carbon::parse($this->forecastDate, $this->session->venue?->timezone ?? 'Europe/Berlin'),
                    (float) $photo->latitude,
                    (float) $photo->longitude,
                    $profile
                );
            }

            $yearOverview = SunShadeForecast::query()
                ->where('detected_table_id', $table->id)
                ->orderBy('forecast_date')
                ->get()
                ->filter(fn ($f) => $f->forecast_date->day === 15);
        }

        return view('livewire.photo-session-show', [
            'table' => $table,
            'dayForecast' => $dayForecast,
            'yearOverview' => $yearOverview,
            'title' => $this->session->title ?? 'Foto-Session',
        ]);
    }
}
