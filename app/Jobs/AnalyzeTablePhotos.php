<?php

namespace App\Jobs;

use App\Models\PhotoSession;
use App\Services\Sun\TablePhotoAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class AnalyzeTablePhotos implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $photoSessionId) {}

    public function handle(TablePhotoAnalyzer $analyzer): void
    {
        $session = PhotoSession::query()->find($this->photoSessionId);
        if (! $session) {
            return;
        }

        $analyzer->analyze($session);
    }
}
