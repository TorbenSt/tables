<?php

namespace App\Console\Commands;

use App\Models\Venue;
use App\Services\Decisions\DecisionEngine;
use Illuminate\Console\Command;

class RecomputeDecisionsCommand extends Command
{
    protected $signature = 'decisions:recompute {--venue= : Venue ID} {--no-grok : Nur Regel-Engine}';

    protected $description = 'Berechnet Tischfreigaben für 14 Tage neu (Open-Meteo + Umfrageprofil)';

    public function handle(DecisionEngine $engine): int
    {
        $query = Venue::query();
        if ($id = $this->option('venue')) {
            $query->whereKey($id);
        }

        $venues = $query->get();
        if ($venues->isEmpty()) {
            $this->warn('Keine Venues gefunden.');

            return self::FAILURE;
        }

        $useGrok = ! $this->option('no-grok');

        foreach ($venues as $venue) {
            $this->info("Berechne Freigaben für {$venue->name}...");
            $run = $engine->recompute($venue, $useGrok);
            $this->info("  Run #{$run->id} – {$run->entries->count()} Tage, Quelle: {$run->source}");
        }

        return self::SUCCESS;
    }
}
