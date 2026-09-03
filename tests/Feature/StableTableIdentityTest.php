<?php

namespace Tests\Feature;

use App\Models\DetectedTable;
use App\Models\PhotoSession;
use App\Models\TableObservation;
use App\Models\TablePhoto;
use App\Models\Venue;
use App\Services\Sun\TablePhotoAnalyzer;
use App\Support\TableColorPalette;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class StableTableIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function venue(): Venue
    {
        return Venue::query()->create([
            'name' => 'Testcafé',
            'latitude' => 52.52,
            'longitude' => 13.4,
            'tables_indoor' => 10,
            'tables_outdoor_sun' => 5,
            'tables_outdoor_shade' => 5,
            'timezone' => 'Europe/Berlin',
        ]);
    }

    /**
     * @return array{0: PhotoSession, 1: list<TablePhoto>}
     */
    private function sessionWithPhotos(int $count = 3): array
    {
        $venue = $this->venue();
        $session = PhotoSession::query()->create([
            'venue_id' => $venue->id,
            'title' => 'Stabilitätstest',
            'capture_date' => '2026-06-15',
            'status' => 'ready',
            'viewpoint_latitude' => 52.52,
            'viewpoint_longitude' => 13.4,
            'viewpoint_bearing' => 180,
        ]);

        $photos = [];
        $times = ['09:00:00', '13:00:00', '17:00:00'];
        for ($i = 0; $i < $count; $i++) {
            $photos[] = TablePhoto::query()->create([
                'photo_session_id' => $session->id,
                'path' => "photo-sessions/test-{$i}.jpg",
                'captured_at' => $times[$i] ?? sprintf('%02d:00:00', 9 + $i * 4),
                'latitude' => 52.52,
                'longitude' => 13.4,
                'bearing' => 180,
                'sort_order' => $i,
            ]);
        }

        return [$session->fresh('photos'), $photos];
    }

    #[Test]
    public function persist_inventory_creates_one_entity_with_three_observations_and_color(): void
    {
        [$session, $photos] = $this->sessionWithPhotos(3);

        $inventory = [
            'tables' => [
                [
                    'stable_id' => 'T1',
                    'label' => 'Außentisch links',
                    'color_hex' => '#e11d48',
                    'has_umbrella' => true,
                    'umbrella_height_m' => 2.2,
                    'umbrella_radius_m' => 1.5,
                    'appearances' => [
                        [
                            'photo_index' => 1,
                            'bbox' => ['x' => 10, 'y' => 20, 'w' => 15, 'h' => 12],
                            'observed_condition' => 'sun',
                        ],
                        [
                            'photo_index' => 2,
                            'bbox' => ['x' => 11, 'y' => 21, 'w' => 14, 'h' => 11],
                            'observed_condition' => 'shade',
                        ],
                        [
                            'photo_index' => 3,
                            'bbox' => ['x' => 12, 'y' => 22, 'w' => 13, 'h' => 10],
                            'observed_condition' => 'mixed',
                        ],
                    ],
                ],
            ],
        ];

        app(TablePhotoAnalyzer::class)->persistInventory($session, $inventory);

        $this->assertSame(1, DetectedTable::query()->count());
        $table = DetectedTable::query()->first();
        $this->assertSame('T1', $table->stable_key);
        $this->assertSame('#e11d48', $table->color_hex);
        $this->assertSame(3, TableObservation::query()->count());
        $this->assertSame(3, $table->observations()->count());
        $this->assertEqualsCanonicalizing(
            collect($photos)->pluck('id')->all(),
            $table->observations()->pluck('table_photo_id')->all()
        );
    }

    #[Test]
    public function table_only_on_later_photos_gets_entity_without_photo_one_appearance(): void
    {
        [$session, $photos] = $this->sessionWithPhotos(3);

        $inventory = [
            'tables' => [
                [
                    'stable_id' => 'T3',
                    'label' => 'Erst später sichtbar',
                    'color_hex' => '#16a34a',
                    'appearances' => [
                        [
                            'photo_index' => 2,
                            'bbox' => ['x' => 38, 'y' => 55, 'w' => 16, 'h' => 14],
                            'observed_condition' => 'mixed',
                        ],
                        [
                            'photo_index' => 3,
                            'bbox' => ['x' => 40, 'y' => 56, 'w' => 15, 'h' => 13],
                            'observed_condition' => 'sun',
                        ],
                    ],
                ],
            ],
        ];

        app(TablePhotoAnalyzer::class)->persistInventory($session, $inventory);

        $table = DetectedTable::query()->where('stable_key', 'T3')->first();
        $this->assertNotNull($table);
        $this->assertSame(2, $table->observations()->count());
        $this->assertNull($table->observationOnPhoto($photos[0]->id));
        $this->assertNotNull($table->observationOnPhoto($photos[1]->id));
        $this->assertNotNull($table->observationOnPhoto($photos[2]->id));
    }

    #[Test]
    public function missing_color_hex_falls_back_to_palette_from_stable_key(): void
    {
        [$session] = $this->sessionWithPhotos(1);

        app(TablePhotoAnalyzer::class)->persistInventory($session, [
            'tables' => [
                [
                    'stable_id' => 'T2',
                    'label' => 'Ohne Farbe',
                    'appearances' => [
                        [
                            'photo_index' => 1,
                            'bbox' => ['x' => 10, 'y' => 10, 'w' => 10, 'h' => 10],
                            'observed_condition' => 'sun',
                        ],
                    ],
                ],
            ],
        ]);

        $table = DetectedTable::query()->first();
        $this->assertSame(TableColorPalette::forKey('T2'), $table->color_hex);
    }

    #[Test]
    public function fallback_analysis_keeps_stable_ids_and_late_table(): void
    {
        config(['services.xai.key' => null]);
        [$session] = $this->sessionWithPhotos(3);

        $session = app(TablePhotoAnalyzer::class)->analyze($session);

        $keys = $session->detectedTables->pluck('stable_key')->sort()->values()->all();
        $this->assertSame(['T1', 'T2', 'T3'], $keys);

        $t1 = $session->detectedTables->firstWhere('stable_key', 'T1');
        $this->assertSame(3, $t1->observations->count());
        $this->assertSame(TableColorPalette::forKey('T1'), $t1->color_hex);

        $t3 = $session->detectedTables->firstWhere('stable_key', 'T3');
        $this->assertSame(2, $t3->observations->count());
        $this->assertSame(TableColorPalette::forKey('T3'), $t3->color_hex);
    }

    #[Test]
    public function show_page_lists_each_stable_key_once_with_consistent_overlay_color(): void
    {
        [$session, $photos] = $this->sessionWithPhotos(3);

        app(TablePhotoAnalyzer::class)->persistInventory($session, [
            'tables' => [
                [
                    'stable_id' => 'T1',
                    'label' => 'Links',
                    'color_hex' => '#e11d48',
                    'appearances' => [
                        ['photo_index' => 1, 'bbox' => ['x' => 10, 'y' => 20, 'w' => 15, 'h' => 12], 'observed_condition' => 'sun'],
                        ['photo_index' => 2, 'bbox' => ['x' => 11, 'y' => 21, 'w' => 14, 'h' => 11], 'observed_condition' => 'shade'],
                        ['photo_index' => 3, 'bbox' => ['x' => 12, 'y' => 22, 'w' => 13, 'h' => 10], 'observed_condition' => 'mixed'],
                    ],
                ],
                [
                    'stable_id' => 'T3',
                    'label' => 'Hinten',
                    'color_hex' => '#16a34a',
                    'appearances' => [
                        ['photo_index' => 2, 'bbox' => ['x' => 38, 'y' => 55, 'w' => 16, 'h' => 14], 'observed_condition' => 'mixed'],
                        ['photo_index' => 3, 'bbox' => ['x' => 40, 'y' => 56, 'w' => 15, 'h' => 13], 'observed_condition' => 'sun'],
                    ],
                ],
            ],
        ]);

        $t1 = DetectedTable::query()->where('stable_key', 'T1')->firstOrFail();
        $t3 = DetectedTable::query()->where('stable_key', 'T3')->firstOrFail();

        $response = $this->get(route('photo-sessions.show', $session));

        $response->assertOk();
        $html = $response->getContent();

        $this->assertSame(1, substr_count($html, 'value="'.$t1->id.'"'));
        $this->assertSame(3, substr_count($html, 'data-stable-key="T1"'));
        $this->assertSame(2, substr_count($html, 'data-stable-key="T3"'));
        $this->assertStringContainsString('data-color-hex="#e11d48"', $html);
        $this->assertStringContainsString('T1 · Links', $html);

        \Livewire\Livewire::test(\App\Livewire\PhotoSessionShow::class, ['session' => $session])
            ->set('selectedTableId', $t3->id)
            ->assertSee('T3 nicht sichtbar auf Foto 1');
    }
}
