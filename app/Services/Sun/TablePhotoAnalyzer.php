<?php

namespace App\Services\Sun;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\TablePhoto;
use App\Services\Xai\GrokClient;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

class TablePhotoAnalyzer
{
    public function __construct(
        private GrokClient $grok,
        private SunShadePredictor $predictor,
    ) {}

    public function analyze(PhotoSession $session): PhotoSession
    {
        $session->update(['status' => 'processing', 'error_message' => null]);
        $session->load('photos');
        $session->detectedTables()->delete();

        try {
            if ($this->grok->isConfigured()) {
                $this->analyzeWithGrok($session);
            } else {
                $this->analyzeFallback($session);
            }

            $session->refresh()->load(['photos', 'detectedTables']);
            $this->predictor->generateForSession($session);

            $session->update(['status' => 'ready']);
        } catch (Throwable $e) {
            Log::error('Photo analysis failed', ['session' => $session->id, 'error' => $e->getMessage()]);
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $session->fresh(['photos', 'detectedTables.forecasts']);
    }

    private function analyzeWithGrok(PhotoSession $session): void
    {
        $images = [];
        $metaLines = [];
        foreach ($session->photos as $i => $photo) {
            $absolute = Storage::disk('public')->path($photo->path);
            $images[] = ['path' => $absolute];
            $metaLines[] = sprintf(
                'Foto %d: Uhrzeit %s, lat %.6f, lng %.6f, Blickrichtung %.1f°, Schirm-Hinweis: %s',
                $i + 1,
                $photo->captured_at,
                $photo->latitude,
                $photo->longitude,
                $photo->bearing,
                $photo->umbrella_hint ? 'ja' : 'nein'
            );
        }

        $prompt = <<<PROMPT
Du analysierst Fotos einer Gastro-Außenterrasse vom gleichen Tag.
Aufgaben:
1) Erkenne Außentische (keine Indoor-Tische).
2) Für jeden Tisch: Bounding-Box in Prozent der Bildbreite/-höhe (x,y,w,h von 0-100), Label, ob Schirm vorhanden, Schätzungen Schirmhöhe_m und Schirmradius_m, beobachteter Zustand sun|shade|mixed|unknown.
3) Ordne jeden Tisch dem Foto zu (photo_index 1-basiert).

Metadaten:
PROMPT.$metaLines;

        $prompt .= "\n\nJSON-Schema: {\"tables\":[{\"photo_index\":1,\"label\":\"Tisch A\",\"bbox\":{\"x\":10,\"y\":20,\"w\":15,\"h\":12},\"has_umbrella\":true,\"umbrella_height_m\":2.2,\"umbrella_radius_m\":1.5,\"observed_condition\":\"sun\",\"notes\":\"...\"}]}";

        $result = $this->grok->visionJson($prompt, $images);
        $session->update(['analysis_raw' => $result]);

        $photos = $session->photos->values();
        foreach ($result['tables'] ?? [] as $row) {
            $idx = max(1, (int) ($row['photo_index'] ?? 1)) - 1;
            /** @var TablePhoto|null $photo */
            $photo = $photos[$idx] ?? $photos->first();
            $bbox = $row['bbox'] ?? [];

            DetectedTable::query()->create([
                'photo_session_id' => $session->id,
                'table_photo_id' => $photo?->id,
                'label' => $row['label'] ?? 'Außentisch',
                'bbox_x' => (float) ($bbox['x'] ?? 10),
                'bbox_y' => (float) ($bbox['y'] ?? 10),
                'bbox_w' => (float) ($bbox['w'] ?? 20),
                'bbox_h' => (float) ($bbox['h'] ?? 15),
                'has_umbrella' => (bool) ($row['has_umbrella'] ?? false),
                'umbrella_height_m' => $row['umbrella_height_m'] ?? null,
                'umbrella_radius_m' => $row['umbrella_radius_m'] ?? null,
                'observed_condition' => $row['observed_condition'] ?? 'unknown',
                'meta' => ['notes' => $row['notes'] ?? null],
            ]);
        }

        if ($session->detectedTables()->count() === 0) {
            $this->analyzeFallback($session);
        }
    }

    private function analyzeFallback(PhotoSession $session): void
    {
        // Demo detections without API: place sample boxes and alternate sun/shade by photo time
        foreach ($session->photos as $i => $photo) {
            $conditions = ['sun', 'shade', 'mixed'];
            DetectedTable::query()->create([
                'photo_session_id' => $session->id,
                'table_photo_id' => $photo->id,
                'label' => 'Demo-Tisch '.($i + 1).'A',
                'bbox_x' => 15 + ($i * 5),
                'bbox_y' => 35,
                'bbox_w' => 22,
                'bbox_h' => 18,
                'has_umbrella' => $photo->umbrella_hint || $i % 2 === 0,
                'umbrella_height_m' => 2.2,
                'umbrella_radius_m' => 1.6,
                'observed_condition' => $conditions[$i % 3],
                'meta' => ['source' => 'fallback'],
            ]);
            DetectedTable::query()->create([
                'photo_session_id' => $session->id,
                'table_photo_id' => $photo->id,
                'label' => 'Demo-Tisch '.($i + 1).'B',
                'bbox_x' => 55,
                'bbox_y' => 40,
                'bbox_w' => 20,
                'bbox_h' => 16,
                'has_umbrella' => false,
                'umbrella_height_m' => null,
                'umbrella_radius_m' => null,
                'observed_condition' => $conditions[($i + 1) % 3],
                'meta' => ['source' => 'fallback'],
            ]);
        }

        if (! $session->analysis_raw) {
            $session->update([
                'analysis_raw' => [
                    'source' => 'fallback',
                    'note' => 'Kein XAI_API_KEY – Demo-Markierungen erzeugt.',
                ],
            ]);
        }
    }
}
