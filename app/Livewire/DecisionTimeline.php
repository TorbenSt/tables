<?php

namespace App\Livewire;

use App\Models\DecisionEntry;
use App\Models\DecisionRun;
use App\Models\Venue;
use Carbon\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DecisionTimeline extends Component
{
    public Venue $venue;

    public string $category = 'outdoor_sun';

    public string $targetDate = '';

    public ?int $selectedRunId = null;

    public function mount(Venue $venue): void
    {
        $this->venue = $venue;
        $this->targetDate = now()->addDays(3)->toDateString();

        $latest = $venue->decisionRuns()->latest('ran_at')->first();
        if ($latest) {
            $firstEntry = $latest->entries()->orderBy('target_date')->first();
            if ($firstEntry) {
                $this->targetDate = $firstEntry->target_date->toDateString();
            }
        }
    }

    public function updatedTargetDate(): void
    {
        $this->selectedRunId = null;
    }

    public function selectRun(int $runId): void
    {
        $this->selectedRunId = $runId;
    }

    public function render()
    {
        $runs = DecisionRun::query()
            ->where('venue_id', $this->venue->id)
            ->with(['entries' => fn ($q) => $q->whereDate('target_date', $this->targetDate)])
            ->orderBy('ran_at')
            ->get();

        $points = $runs->map(function (DecisionRun $run) {
            /** @var DecisionEntry|null $entry */
            $entry = $run->entries->first();

            return [
                'run_id' => $run->id,
                'ran_at' => $run->ran_at,
                'source' => $run->source,
                'percent' => $entry ? $entry->releaseFor($this->category) : null,
                'scenario_id' => $entry?->matched_scenario_id,
                'reasoning' => $entry?->reasoning,
                'weather_day' => $entry?->weather_day,
                'grok_adjusted' => $entry?->grok_adjusted ?? false,
            ];
        })->filter(fn ($p) => $p['percent'] !== null)->values();

        $selected = $points->firstWhere('run_id', $this->selectedRunId) ?? $points->last();

        $labels = [
            'outdoor_sun' => 'Außen Sonne',
            'outdoor_shade' => 'Außen Schatten',
            'indoor' => 'Innen',
        ];

        return view('livewire.decision-timeline', [
            'points' => $points,
            'selected' => $selected,
            'labels' => $labels,
            'title' => 'Entscheidungstimeline',
            'targetLabel' => Carbon::parse($this->targetDate)->format('d.m.Y'),
        ]);
    }
}
