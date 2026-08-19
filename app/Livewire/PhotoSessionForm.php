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

    /** @var array<int, mixed> */
    public array $photos = [];

    /** @var array<int, array{time: string, latitude: string, longitude: string, bearing: string, umbrella_hint: bool, source: string}> */
    public array $meta = [];

    public bool $syncAnalyze = true;

    public function mount(): void
    {
        $this->capture_date = now()->toDateString();
        $this->venue_id = Venue::query()->value('id');
        $this->addPhotoSlot();
        $this->addPhotoSlot();
        $this->addPhotoSlot();
    }

    public function addPhotoSlot(): void
    {
        $this->photos[] = null;
        $defaultLat = Venue::query()->value('latitude') ?? '52.520008';
        $defaultLng = Venue::query()->value('longitude') ?? '13.404954';
        $this->meta[] = [
            'time' => now()->addMinutes(count($this->meta) * 45)->format('H:i'),
            'latitude' => (string) $defaultLat,
            'longitude' => (string) $defaultLng,
            'bearing' => '',
            'umbrella_hint' => false,
            'source' => '',
        ];
    }

    public function updatedPhotos(mixed $value, mixed $key): void
    {
        $index = (int) $key;
        $file = $this->photos[$index] ?? null;
        if (! $file instanceof TemporaryUploadedFile) {
            return;
        }

        if (str_starts_with((string) ($this->meta[$index]['source'] ?? ''), 'kamera')) {
            return;
        }

        $extracted = app(ExifMetaExtractor::class)->fromPath($file->getRealPath());
        if ($extracted === []) {
            return;
        }

        if (isset($extracted['latitude'], $extracted['longitude'])) {
            $this->meta[$index]['latitude'] = (string) $extracted['latitude'];
            $this->meta[$index]['longitude'] = (string) $extracted['longitude'];
        }
        if (isset($extracted['time'])) {
            $this->meta[$index]['time'] = $extracted['time'];
        }
        if (isset($extracted['date'])) {
            $this->capture_date = $extracted['date'];
        }
        if (isset($extracted['bearing'])) {
            $this->meta[$index]['bearing'] = (string) round($extracted['bearing'], 1);
        }

        $bits = [];
        if (isset($extracted['latitude'])) {
            $bits[] = 'GPS';
        }
        if (isset($extracted['bearing'])) {
            $bits[] = 'Richtung';
        }
        if (isset($extracted['time'])) {
            $bits[] = 'Uhrzeit';
        }
        if ($bits !== [] && ($this->meta[$index]['source'] ?? '') !== 'kamera') {
            $this->meta[$index]['source'] = 'EXIF: '.implode(', ', $bits);
        }
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
        $this->validate([
            'capture_date' => 'required|date',
            'title' => 'nullable|string|max:120',
            'venue_id' => 'nullable|exists:venues,id',
            'photos' => 'required|array|min:3',
            'photos.*' => 'required|image|max:10240',
            'meta' => 'required|array|min:3',
            'meta.*.time' => 'required|date_format:H:i',
            'meta.*.latitude' => 'required|numeric|between:-90,90',
            'meta.*.longitude' => 'required|numeric|between:-180,180',
            'meta.*.bearing' => 'required|numeric|between:0,360',
            'meta.*.umbrella_hint' => 'boolean',
            'meta.*.source' => 'nullable|string|max:80',
        ], [
            'photos.min' => 'Mindestens 3 Fotos erforderlich.',
            'photos.*.required' => 'Jedes Slot braucht ein Foto.',
        ]);

        $times = collect($this->meta)->pluck('time')->unique();
        if ($times->count() < 3) {
            $this->addError('meta', 'Die Fotos müssen unterschiedliche Uhrzeiten haben.');

            return;
        }

        $session = PhotoSession::query()->create([
            'venue_id' => $this->venue_id,
            'title' => $this->title ?: 'Session '.$this->capture_date,
            'capture_date' => $this->capture_date,
            'status' => 'pending',
        ]);

        foreach ($this->photos as $i => $upload) {
            $path = $upload->store('table-photos/'.$session->id, 'public');
            TablePhoto::query()->create([
                'photo_session_id' => $session->id,
                'path' => $path,
                'captured_at' => $this->meta[$i]['time'].':00',
                'latitude' => $this->meta[$i]['latitude'],
                'longitude' => $this->meta[$i]['longitude'],
                'bearing' => $this->meta[$i]['bearing'],
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
            'title' => 'Tisch-Fotos hochladen',
        ]);
    }
}
