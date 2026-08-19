<?php

namespace App\Livewire;

use App\Services\Decisions\SurveyAggregator;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SurveyResults extends Component
{
    public function render(SurveyAggregator $aggregator)
    {
        return view('livewire.survey-results', [
            'profile' => $aggregator->profile(),
            'count' => $aggregator->responseCount(),
            'scenarios' => config('survey_scenarios'),
            'title' => 'Umfrage-Auswertung',
        ]);
    }
}
