<?php

namespace App\Livewire;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\SunShadeForecast;
use App\Services\Sun\TablePhotoAnalyzer;
use App\Services\Sun\SunShadePredictor;
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
        if ($session->status === 'draft') {
            $this->redirect(route('photo-sessions.camera.continue', $session), navigate: true);

            return;
        }

        $this->session = $session->load(['photos', 'detectedTables.observations', 'venue']);
        $this->selectedTableId = $session->detectedTables->sortBy('stable_key')->first()?->id;
        $this->forecastDate = now()->format('Y-m-15');
    }

    public function selectTable(int $id): void
    {
        $this->selectedTableId = $id;
    }

    public function reanalyze(TablePhotoAnalyzer $analyzer): void
    {
        $analyzer->analyze($this->session);
        $this->session->refresh()->load(['photos', 'detectedTables.observations', 'venue']);
        $this->selectedTableId = $this->session->detectedTables->sortBy('stable_key')->first()?->id;
        session()->flash('status', 'Analyse erneut ausgeführt.');
    }

    public function render()
    {
        $this->session->loadMissing(['photos', 'detectedTables.observations', 'venue']);
        $tables = $this->session->detectedTables->sortBy('stable_key')->values();

        $table = $this->selectedTableId
            ? DetectedTable::query()->with(['observations.photo', 'photo'])->find($this->selectedTableId)
            : null;

        $dayForecast = null;
        $yearOverview = collect();
        $visibilityNotes = [];

        if ($table) {
            foreach ($this->session->photos as $i => $photo) {
                if (! $table->observationOnPhoto($photo->id)) {
                    $visibilityNotes[] = sprintf(
                        '%s nicht sichtbar auf Foto %d',
                        $table->stable_key ?: $table->label,
                        $i + 1
                    );
                }
            }

            $dayForecast = SunShadeForecast::query()
                ->where('detected_table_id', $table->id)
                ->whereDate('forecast_date', $this->forecastDate)
                ->first();

            if (! $dayForecast && $this->session->photos->isNotEmpty()) {
                $predictor = app(SunShadePredictor::class);
                $profile = $predictor->buildExposureProfileForTable($this->session, $table);
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
            'tables' => $tables,
            'dayForecast' => $dayForecast,
            'yearOverview' => $yearOverview,
            'visibilityNotes' => $visibilityNotes,
            'title' => $this->session->title ?? 'Foto-Session',
        ]);
    }
}
