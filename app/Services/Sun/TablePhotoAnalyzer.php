<?php

namespace App\Services\Sun;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\TableObservation;
use App\Models\TablePhoto;
use App\Services\Xai\GrokClient;
use App\Support\TableColorPalette;
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
        $session->detectedTables()->each(function (DetectedTable $table) {
            $table->observations()->delete();
            $table->forecasts()->delete();
            $table->delete();
        });

        try {
            if ($this->grok->isConfigured()) {
                $this->analyzeWithGrok($session);
            } else {
                $this->analyzeFallback($session);
            }

            $session->refresh()->load(['photos', 'detectedTables.observations']);
            $this->predictor->generateForSession($session);

            $session->update(['status' => 'ready']);
        } catch (Throwable $e) {
            Log::error('Photo analysis failed', ['session' => $session->id, 'error' => $e->getMessage()]);
            $session->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
            ]);
        }

        return $session->fresh(['photos', 'detectedTables.observations', 'detectedTables.forecasts']);
    }

    /**
     * Persist inventory JSON (Grok or fallback) into stable tables + observations.
     *
     * @param  array<string, mixed>  $result
     */
    public function persistInventory(PhotoSession $session, array $result): void
    {
        $photos = $session->photos->values();
        $usedKeys = [];

        foreach ($result['tables'] ?? [] as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $stableKey = $this->normalizeStableKey($row['stable_id'] ?? null, $i);
            while (isset($usedKeys[$stableKey])) {
                $stableKey = $this->normalizeStableKey(null, count($usedKeys));
            }
            $usedKeys[$stableKey] = true;

            $appearances = $row['appearances'] ?? [];
            if (! is_array($appearances) || $appearances === []) {
                // Legacy flat schema compatibility
                if (isset($row['photo_index'], $row['bbox'])) {
                    $appearances = [[
                        'photo_index' => $row['photo_index'],
                        'bbox' => $row['bbox'],
                        'observed_condition' => $row['observed_condition'] ?? 'unknown',
                    ]];
                } else {
                    continue;
                }
            }

            $firstAppearance = $appearances[0] ?? null;
            $firstIdx = max(1, (int) ($firstAppearance['photo_index'] ?? 1)) - 1;
            $firstPhoto = $photos[$firstIdx] ?? $photos->first();
            $firstBbox = is_array($firstAppearance['bbox'] ?? null) ? $firstAppearance['bbox'] : [];

            $table = DetectedTable::query()->create([
                'photo_session_id' => $session->id,
                'table_photo_id' => $firstPhoto?->id,
                'stable_key' => $stableKey,
                'color_hex' => TableColorPalette::normalize(
                    isset($row['color_hex']) ? (string) $row['color_hex'] : null,
                    $stableKey
                ),
                'label' => $row['label'] ?? 'Außentisch '.$stableKey,
                'bbox_x' => (float) ($firstBbox['x'] ?? 10),
                'bbox_y' => (float) ($firstBbox['y'] ?? 10),
                'bbox_w' => (float) ($firstBbox['w'] ?? 20),
                'bbox_h' => (float) ($firstBbox['h'] ?? 15),
                'has_umbrella' => (bool) ($row['has_umbrella'] ?? false),
                'umbrella_height_m' => $row['umbrella_height_m'] ?? null,
                'umbrella_radius_m' => $row['umbrella_radius_m'] ?? null,
                'observed_condition' => $firstAppearance['observed_condition'] ?? 'unknown',
                'meta' => ['notes' => $row['notes'] ?? null],
            ]);

            $seenPhotos = [];
            foreach ($appearances as $appearance) {
                if (! is_array($appearance)) {
                    continue;
                }
                $idx = max(1, (int) ($appearance['photo_index'] ?? 1)) - 1;
                /** @var TablePhoto|null $photo */
                $photo = $photos[$idx] ?? null;
                if (! $photo || isset($seenPhotos[$photo->id])) {
                    continue;
                }
                $seenPhotos[$photo->id] = true;

                $bbox = is_array($appearance['bbox'] ?? null) ? $appearance['bbox'] : [];
                TableObservation::query()->create([
                    'detected_table_id' => $table->id,
                    'table_photo_id' => $photo->id,
                    'bbox_x' => (float) ($bbox['x'] ?? 10),
                    'bbox_y' => (float) ($bbox['y'] ?? 10),
                    'bbox_w' => (float) ($bbox['w'] ?? 20),
                    'bbox_h' => (float) ($bbox['h'] ?? 15),
                    'observed_condition' => $appearance['observed_condition'] ?? 'unknown',
                    'meta' => ['notes' => $appearance['notes'] ?? null],
                ]);
            }
        }
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

        $metaBlock = implode("\n", $metaLines);

        $prompt = <<<PROMPT
Du analysierst mehrere Fotos derselben Gastro-Außenterrasse (gleicher Standpunkt, gleiche Blickrichtung, unterschiedliche Uhrzeiten).

Hauptaufgabe: MULTI-FOTO-KORRESPONDENZ.
1) Erstelle ein Inventar physischer Außentische (keine Indoor-Tische).
2) Derselbe physische Tisch über alle Fotos = dieselbe stable_id (T1, T2, …).
3) Matche nach relativer Lage (links→rechts, vorne→hinten) und Ankern (Schirme, Markisen, Bodenmuster).
4) Inventory = Union aller sichtbaren Außentische. Fehlt Sichtbarkeit auf einem Foto → Entity behalten, Appearance weglassen.
5) Neu erst auf Foto 2/3 sichtbar → neue stable_id, Appearances nur dort.
6) Jeder Tisch bekommt eine eindeutige kontrastreiche color_hex (#RRGGBB).
7) Pro Appearance: Bounding-Box in % (x,y,w,h 0–100) und observed_condition sun|shade|mixed|unknown.

Metadaten:
{$metaBlock}

Antworte ausschließlich mit JSON nach diesem Schema:
{"tables":[{"stable_id":"T1","label":"Außentisch links vorne","color_hex":"#e11d48","has_umbrella":true,"umbrella_height_m":2.2,"umbrella_radius_m":1.5,"notes":null,"appearances":[{"photo_index":1,"bbox":{"x":10,"y":20,"w":15,"h":12},"observed_condition":"sun"},{"photo_index":2,"bbox":{"x":12,"y":22,"w":14,"h":11},"observed_condition":"shade"}]}]}
PROMPT;

        $result = $this->grok->visionJson($prompt, $images);
        $session->update(['analysis_raw' => $result]);
        $this->persistInventory($session, $result);

        if ($session->detectedTables()->count() === 0) {
            $this->analyzeFallback($session);
        }
    }

    private function analyzeFallback(PhotoSession $session): void
    {
        $photos = $session->photos->values();
        $conditions = ['sun', 'shade', 'mixed'];

        $inventory = [
            'tables' => [
                [
                    'stable_id' => 'T1',
                    'label' => 'Außentisch links',
                    'color_hex' => TableColorPalette::forKey('T1'),
                    'has_umbrella' => true,
                    'umbrella_height_m' => 2.2,
                    'umbrella_radius_m' => 1.6,
                    'appearances' => $photos->map(fn (TablePhoto $photo, int $i) => [
                        'photo_index' => $i + 1,
                        'bbox' => ['x' => 15.0, 'y' => 35.0, 'w' => 22.0, 'h' => 18.0],
                        'observed_condition' => $conditions[$i % 3],
                    ])->all(),
                ],
                [
                    'stable_id' => 'T2',
                    'label' => 'Außentisch rechts',
                    'color_hex' => TableColorPalette::forKey('T2'),
                    'has_umbrella' => false,
                    'appearances' => $photos->map(fn (TablePhoto $photo, int $i) => [
                        'photo_index' => $i + 1,
                        'bbox' => ['x' => 55.0, 'y' => 40.0, 'w' => 20.0, 'h' => 16.0],
                        'observed_condition' => $conditions[($i + 1) % 3],
                    ])->all(),
                ],
            ],
            'source' => 'fallback',
            'note' => 'Kein XAI_API_KEY – Demo-Inventar mit stabilen IDs.',
        ];

        // Extra table only visible from photo 2 onward
        if ($photos->count() >= 2) {
            $lateAppearances = [];
            foreach ($photos as $i => $photo) {
                if ($i === 0) {
                    continue;
                }
                $lateAppearances[] = [
                    'photo_index' => $i + 1,
                    'bbox' => ['x' => 38.0, 'y' => 55.0, 'w' => 16.0, 'h' => 14.0],
                    'observed_condition' => 'mixed',
                ];
            }
            $inventory['tables'][] = [
                'stable_id' => 'T3',
                'label' => 'Außentisch hinten (erst später sichtbar)',
                'color_hex' => TableColorPalette::forKey('T3'),
                'has_umbrella' => false,
                'appearances' => $lateAppearances,
            ];
        }

        $session->update(['analysis_raw' => $inventory]);
        $this->persistInventory($session, $inventory);
    }

    private function normalizeStableKey(mixed $raw, int $index): string
    {
        if (is_string($raw) && preg_match('/^T?\d+$/i', trim($raw))) {
            $n = (int) ltrim(strtoupper(trim($raw)), 'T');

            return 'T'.max(1, $n);
        }
        if (is_string($raw) && preg_match('/[A-Za-z0-9_-]{1,16}/', $raw, $m)) {
            return strtoupper($m[0]);
        }

        return 'T'.($index + 1);
    }
}
