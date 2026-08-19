<?php

namespace App\Livewire;

use App\Models\DecisionRun;
use App\Models\Venue;
use App\Services\Decisions\DecisionEngine;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class DecisionCalendar extends Component
{
    public Venue $venue;

    public ?int $latestRunId = null;

    public bool $computing = false;

    public function mount(Venue $venue): void
    {
        $this->venue = $venue;
        $this->latestRunId = $venue->decisionRuns()->latest('ran_at')->value('id');
    }

    public function recompute(DecisionEngine $engine): void
    {
        $this->computing = true;
        $run = $engine->recompute($this->venue, true);
        $this->latestRunId = $run->id;
        $this->computing = false;
        session()->flash('status', "Freigaben neu berechnet (Run #{$run->id}, Quelle: {$run->source}).");
    }

    public function render()
    {
        $run = $this->latestRunId
            ? DecisionRun::query()->with('entries')->find($this->latestRunId)
            : null;

        return view('livewire.decision-calendar', [
            'run' => $run,
            'title' => 'Freigaben 14 Tage',
        ]);
    }
}
