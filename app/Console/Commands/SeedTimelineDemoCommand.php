<?php

namespace App\Console\Commands;

use App\Models\DecisionEntry;
use App\Models\DecisionRun;
use App\Models\Venue;
use Illuminate\Console\Command;

class SeedTimelineDemoCommand extends Command
{
    protected $signature = 'decisions:seed-timeline-demo {--venue=1} {--days-ago=7} {--shift=15}';

    protected $description = 'Legt einen historischen Decision-Run an (für Timeline-Demo)';

    public function handle(): int
    {
        $venue = Venue::query()->find($this->option('venue'));
        if (! $venue) {
            $this->error('Venue nicht gefunden.');

            return self::FAILURE;
        }

        $latest = $venue->decisionRuns()->latest('ran_at')->first();
        if (! $latest) {
            $this->error('Kein aktueller Run vorhanden. Zuerst: php artisan decisions:recompute');

            return self::FAILURE;
        }

        $latest->load('entries');
        $shift = (int) $this->option('shift');
        $daysAgo = (int) $this->option('days-ago');

        $old = DecisionRun::query()->create([
            'venue_id' => $venue->id,
            'weather_snapshot_id' => $latest->weather_snapshot_id,
            'source' => 'rules',
            'ran_at' => now()->subDays($daysAgo),
            'notes' => "Demo: historischer Lauf vor {$daysAgo} Tagen",
        ]);

        foreach ($latest->entries as $e) {
            DecisionEntry::query()->create([
                'decision_run_id' => $old->id,
                'target_date' => $e->target_date,
                'matched_scenario_id' => $e->matched_scenario_id,
                'outdoor_sun' => max(0, min(100, $e->outdoor_sun + $shift)),
                'outdoor_shade' => max(0, min(100, $e->outdoor_shade + (int) round($shift / 2))),
                'indoor' => $e->indoor,
                'weather_day' => array_merge($e->weather_day ?? [], ['demo_shift' => $shift]),
                'reasoning' => "Historischer Demo-Lauf: Freigaben um ca. {$shift} Punkte anders (simulierte Wetteränderung).",
                'grok_adjusted' => false,
            ]);
        }

        $this->info("Historischer Run #{$old->id} mit {$old->entries()->count()} Einträgen angelegt.");

        return self::SUCCESS;
    }
}
