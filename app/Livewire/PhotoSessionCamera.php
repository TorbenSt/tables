<?php

namespace App\Livewire;

use App\Jobs\AnalyzeTablePhotos;
use App\Models\PhotoSession;
use App\Models\TablePhoto;
use App\Models\Venue;
use App\Services\Sun\TablePhotoAnalyzer;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class PhotoSessionCamera extends Component
{
    use WithFileUploads;

    public ?int $sessionId = null;

    public string $title = '';

    public string $capture_date = '';

    public ?int $venue_id = null;

    public string $latitude = '';

    public string $longitude = '';

    public string $bearing = '';

    public bool $viewpointLocked = false;

    /** keep = Standpunkt beibehalten, recapture = GPS/Kompass für dieses Foto neu */
    public string $shotViewpoint = 'keep';

    public mixed $shot = null;

    public bool $syncAnalyze = true;

    public function mount($session = null): void
    {
        $venue = Venue::query()->first();
        $this->venue_id = $venue?->id;
        $this->capture_date = now()->toDateString();
        $this->latitude = (string) ($venue?->latitude ?? '');
        $this->longitude = (string) ($venue?->longitude ?? '');

        if ($session) {
            $model = $session instanceof PhotoSession
                ? $session
                : PhotoSession::query()->findOrFail($session);
            abort_unless($model->status === 'draft', 404);
            $this->hydrateSession($model);
        }
    }

    public function lockViewpoint(): void
    {
        $this->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'bearing' => 'required|numeric|between:0,360',
            'capture_date' => 'required|date',
            'venue_id' => 'nullable|exists:venues,id',
            'title' => 'nullable|string|max:120',
        ], [
            'bearing.required' => 'Bitte die Blickrichtung setzen (Kompass am Handy oder Zeiger ziehen).',
        ]);

        $session = $this->session();
        if (! $session) {
            $session = PhotoSession::query()->create([
                'venue_id' => $this->venue_id,
                'title' => $this->title ?: 'Kamera '.$this->capture_date,
                'capture_date' => $this->capture_date,
                'status' => 'draft',
            ]);
            $this->sessionId = $session->id;
        }

        $session->update([
            'title' => $this->title ?: $session->title,
            'capture_date' => $this->capture_date,
            'venue_id' => $this->venue_id,
            'viewpoint_latitude' => $this->latitude,
            'viewpoint_longitude' => $this->longitude,
            'viewpoint_bearing' => $this->bearing,
        ]);

        $this->viewpointLocked = true;
        $this->shotViewpoint = 'keep';
    }

    public function applyDeviceFix(string $deviceLat, string $deviceLng, string $deviceBearing): void
    {
        if ($deviceLat !== '') {
            $this->latitude = $deviceLat;
        }
        if ($deviceLng !== '') {
            $this->longitude = $deviceLng;
        }
        if ($deviceBearing !== '') {
            $this->bearing = $deviceBearing;
        }
    }

    public function storeShot(string $deviceLat = '', string $deviceLng = '', string $deviceBearing = '', string $deviceTime = ''): void
    {
        if (! $this->shot instanceof TemporaryUploadedFile) {
            $this->addError('shot', 'Kein Foto angekommen.');

            return;
        }

        $session = $this->session();
        if (! $session || ! $this->viewpointLocked) {
            $this->addError('shot', 'Zuerst den Standpunkt merken.');

            return;
        }

        $useDevice = $this->shotViewpoint === 'recapture';
        $lat = $useDevice && $deviceLat !== '' ? $deviceLat : (string) $session->viewpoint_latitude;
        $lng = $useDevice && $deviceLng !== '' ? $deviceLng : (string) $session->viewpoint_longitude;
        $dir = $useDevice && $deviceBearing !== '' ? $deviceBearing : (string) $session->viewpoint_bearing;
        $capturedAt = $deviceTime !== '' ? $deviceTime : now()->format('H:i');

        $existing = $session->photos()->pluck('captured_at')->map(fn ($t) => substr((string) $t, 0, 5));
        if ($existing->contains($capturedAt)) {
            $this->addError('shot', "Zu dieser Uhrzeit ({$capturedAt}) gibt es schon ein Foto. Warte etwas oder nimm zu einer anderen Tageszeit auf.");
            $this->shot = null;

            return;
        }

        $sort = (int) $session->photos()->max('sort_order') + 1;
        $path = $this->shot->store('table-photos/'.$session->id, 'public');

        TablePhoto::query()->create([
            'photo_session_id' => $session->id,
            'path' => $path,
            'captured_at' => $capturedAt.':00',
            'latitude' => $lat,
            'longitude' => $lng,
            'bearing' => $dir,
            'umbrella_hint' => false,
            'sort_order' => $sort,
        ]);

        $this->shot = null;
        $this->shotViewpoint = 'keep';
        $this->resetErrorBag('shot');
    }

    public function removeShot(int $photoId): void
    {
        $session = $this->session();
        if (! $session) {
            return;
        }
        $session->photos()->whereKey($photoId)->delete();
    }

    public function finish(TablePhotoAnalyzer $analyzer): void
    {
        $session = $this->session();
        if (! $session) {
            return;
        }

        $session->load('photos');
        if ($session->photos->count() < 3) {
            $this->addError('shot', 'Mindestens 3 Fotos zu unterschiedlichen Uhrzeiten.');

            return;
        }

        $times = $session->photos->map(fn (TablePhoto $p) => substr((string) $p->captured_at, 0, 5))->unique();
        if ($times->count() < 3) {
            $this->addError('shot', 'Die drei Fotos brauchen unterschiedliche Uhrzeiten.');

            return;
        }

        $session->update(['status' => 'pending']);

        if ($this->syncAnalyze) {
            $analyzer->analyze($session);
        } else {
            AnalyzeTablePhotos::dispatch($session->id);
        }

        session()->flash('status', 'Kamera-Session gespeichert und Analyse gestartet.');
        $this->redirect(route('photo-sessions.show', $session), navigate: true);
    }

    public function render()
    {
        $session = $this->session();

        return view('livewire.photo-session-camera', [
            'venues' => Venue::query()->orderBy('name')->get(),
            'photos' => $session?->photos()->get() ?? collect(),
            'title' => 'Mit Handy aufnehmen',
        ]);
    }

    private function session(): ?PhotoSession
    {
        if (! $this->sessionId) {
            return null;
        }

        return PhotoSession::query()->find($this->sessionId);
    }

    private function hydrateSession(PhotoSession $session): void
    {
        $this->sessionId = $session->id;
        $this->title = (string) $session->title;
        $this->capture_date = $session->capture_date->toDateString();
        $this->venue_id = $session->venue_id;
        $this->latitude = $session->viewpoint_latitude !== null ? (string) $session->viewpoint_latitude : $this->latitude;
        $this->longitude = $session->viewpoint_longitude !== null ? (string) $session->viewpoint_longitude : $this->longitude;
        $this->bearing = $session->viewpoint_bearing !== null ? (string) $session->viewpoint_bearing : '';
        $this->viewpointLocked = $session->viewpoint_latitude !== null && $session->viewpoint_bearing !== null;
    }
}
