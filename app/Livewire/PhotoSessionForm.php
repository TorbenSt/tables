<?php

namespace App\Livewire;

use App\Jobs\AnalyzeTablePhotos;
use App\Models\PhotoSession;
use App\Models\TablePhoto;
use App\Models\Venue;
use App\Services\Sun\ExifMetaExtractor;
use App\Services\Sun\TablePhotoAnalyzer;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class PhotoSessionForm extends Component
{
    use WithFileUploads;

    public string $title = '';

    public string $capture_date = '';

    public ?int $venue_id = null;

    public string $sharedLatitude = '';

    public string $sharedLongitude = '';

    public string $sharedBearing = '';

    /** shared = ein Standpunkt für alle, per_photo = je Foto überschreibbar */
    public string $viewpointMode = 'shared';

    /** @var array<int, mixed> */
    public array $photos = [];

    /** @var array<int, array{time: string, latitude: string, longitude: string, bearing: string, umbrella_hint: bool, source: string}> */
    public array $meta = [];

    public bool $syncAnalyze = true;

    public function mount(): void
    {
        $venue = Venue::query()->first();
        $this->capture_date = now()->toDateString();
        $this->venue_id = $venue?->id;
        $this->sharedLatitude = (string) ($venue?->latitude ?? '52.520008');
        $this->sharedLongitude = (string) ($venue?->longitude ?? '13.404954');
        $this->addPhotoSlot();
        $this->addPhotoSlot();
        $this->addPhotoSlot();
    }

    public function addPhotoSlot(): void
    {
        $this->photos[] = null;
        $this->meta[] = [
            'time' => now()->addMinutes(count($this->meta) * 90)->format('H:i'),
            'latitude' => $this->sharedLatitude,
            'longitude' => $this->sharedLongitude,
            'bearing' => $this->sharedBearing,
            'umbrella_hint' => false,
            'source' => '',
        ];
    }

    public function updatedViewpointMode(): void
    {
        if ($this->viewpointMode !== 'per_photo') {
            return;
        }

        foreach ($this->meta as $i => $_) {
            $this->meta[$i]['latitude'] = $this->sharedLatitude;
            $this->meta[$i]['longitude'] = $this->sharedLongitude;
            $this->meta[$i]['bearing'] = $this->sharedBearing;
        }
    }

    public function updatedPhotos(mixed $value, mixed $key): void
    {
        $index = (int) $key;
        $file = $this->photos[$index] ?? null;
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        $extracted = app(ExifMetaExtractor::class)->fromPath($file->getRealPath());
        if ($extracted === []) {
            return;
        }

        if (isset($extracted['time'])) {
            $this->meta[$index]['time'] = $extracted['time'];
        }
        if (isset($extracted['date'])) {
            $this->capture_date = $extracted['date'];
        }

        if ($this->viewpointMode === 'per_photo') {
            if (isset($extracted['latitude'], $extracted['longitude'])) {
                $this->meta[$index]['latitude'] = (string) $extracted['latitude'];
                $this->meta[$index]['longitude'] = (string) $extracted['longitude'];
            }
            if (isset($extracted['bearing'])) {
                $this->meta[$index]['bearing'] = (string) round($extracted['bearing'], 1);
            }
        }

        $bits = ['Uhrzeit'];
        if ($this->viewpointMode === 'per_photo' && isset($extracted['latitude'])) {
            $bits[] = 'GPS';
        }
        $this->meta[$index]['source'] = 'EXIF: '.implode(', ', $bits);
    }

    public function removePhotoSlot(int $index): void
    {
        if (count($this->photos) <= 3) {
            return;
        }
        unset($this->photos[$index], $this->meta[$index]);
        $this->photos = array_values($this->photos);
        $this->meta = array_values($this->meta);
    }

    public function save(TablePhotoAnalyzer $analyzer): void
    {
        $rules = [
            'capture_date' => 'required|date',
            'title' => 'nullable|string|max:120',
            'venue_id' => 'nullable|exists:venues,id',
            'viewpointMode' => 'required|in:shared,per_photo',
            'photos' => 'required|array|min:3',
            'photos.*' => 'required|image|max:10240',
            'meta' => 'required|array|min:3',
            'meta.*.time' => 'required|date_format:H:i',
            'meta.*.umbrella_hint' => 'boolean',
        ];

        if ($this->viewpointMode === 'shared') {
            $rules['sharedLatitude'] = 'required|numeric|between:-90,90';
            $rules['sharedLongitude'] = 'required|numeric|between:-180,180';
            $rules['sharedBearing'] = 'required|numeric|between:0,360';
        } else {
            $rules['meta.*.latitude'] = 'required|numeric|between:-90,90';
            $rules['meta.*.longitude'] = 'required|numeric|between:-180,180';
            $rules['meta.*.bearing'] = 'required|numeric|between:0,360';
        }

        $this->validate($rules, [
            'photos.min' => 'Mindestens 3 Fotos erforderlich.',
            'photos.*.required' => 'Jedes Slot braucht ein Foto.',
            'sharedBearing.required' => 'Bitte die gemeinsame Blickrichtung auf dem Kompass setzen.',
        ]);

        $times = collect($this->meta)->pluck('time')->unique();
        if ($times->count() < 3) {
            $this->addError('meta', 'Die Fotos müssen unterschiedliche Uhrzeiten haben (idealerweise morgens, mittags, nachmittags).');

            return;
        }

        $session = PhotoSession::query()->create([
            'venue_id' => $this->venue_id,
            'title' => $this->title ?: 'Galerie '.$this->capture_date,
            'capture_date' => $this->capture_date,
            'viewpoint_latitude' => $this->sharedLatitude,
            'viewpoint_longitude' => $this->sharedLongitude,
            'viewpoint_bearing' => $this->sharedBearing !== '' ? $this->sharedBearing : null,
            'status' => 'pending',
        ]);

        foreach ($this->photos as $i => $upload) {
            $path = $upload->store('table-photos/'.$session->id, 'public');
            $lat = $this->viewpointMode === 'shared' ? $this->sharedLatitude : $this->meta[$i]['latitude'];
            $lng = $this->viewpointMode === 'shared' ? $this->sharedLongitude : $this->meta[$i]['longitude'];
            $bearing = $this->viewpointMode === 'shared' ? $this->sharedBearing : $this->meta[$i]['bearing'];

            TablePhoto::query()->create([
                'photo_session_id' => $session->id,
                'path' => $path,
                'captured_at' => $this->meta[$i]['time'].':00',
                'latitude' => $lat,
                'longitude' => $lng,
                'bearing' => $bearing,
                'umbrella_hint' => (bool) ($this->meta[$i]['umbrella_hint'] ?? false),
                'sort_order' => $i,
            ]);
        }

        if ($this->syncAnalyze) {
            $analyzer->analyze($session);
        } else {
            AnalyzeTablePhotos::dispatch($session->id);
        }

        session()->flash('status', 'Foto-Session angelegt und Analyse gestartet.');

        $this->redirect(route('photo-sessions.show', $session), navigate: true);
    }

    public function render()
    {
        return view('livewire.photo-session-form', [
            'venues' => Venue::query()->orderBy('name')->get(),
            'title' => 'Galerie hochladen',
        ]);
    }
}
