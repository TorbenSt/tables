<?php

namespace Database\Seeders;

use App\Models\PhotoSession;
use App\Models\TablePhoto;
use App\Models\Venue;
use App\Services\Sun\TablePhotoAnalyzer;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class DemoPhotoSessionSeeder extends Seeder
{
    public function run(): void
    {
        $venue = Venue::query()->first();
        if (! $venue) {
            return;
        }

        $dir = storage_path('app/public/table-photos/demo');
        File::ensureDirectoryExists($dir);

        for ($i = 1; $i <= 3; $i++) {
            $path = "$dir/p$i.jpg";
            if (! file_exists($path) && function_exists('imagecreatetruecolor')) {
                $im = imagecreatetruecolor(640, 480);
                $bg = imagecolorallocate($im, 180 + $i * 10, 200, 160);
                imagefill($im, 0, 0, $bg);
                $t = imagecolorallocate($im, 40, 40, 40);
                imagestring($im, 5, 20, 20, "Demo terrace photo $i", $t);
                imagejpeg($im, $path, 80);
                imagedestroy($im);
            }
        }

        if (PhotoSession::query()->where('title', 'Demo-Terrasse')->exists()) {
            return;
        }

        $session = PhotoSession::query()->create([
            'venue_id' => $venue->id,
            'title' => 'Demo-Terrasse',
            'capture_date' => now()->toDateString(),
            'status' => 'pending',
        ]);

        foreach ([1, 2, 3] as $i) {
            TablePhoto::query()->create([
                'photo_session_id' => $session->id,
                'path' => "table-photos/demo/p{$i}.jpg",
                'captured_at' => sprintf('%02d:00:00', 9 + $i * 2),
                'latitude' => $venue->latitude,
                'longitude' => $venue->longitude,
                'bearing' => 90 + $i * 30,
                'umbrella_hint' => $i === 1,
                'sort_order' => $i,
            ]);
        }

        app(TablePhotoAnalyzer::class)->analyze($session);
    }
}
