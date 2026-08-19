<?php

namespace App\Livewire;

use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class SurveyForm extends Component
{
    public int $step = 0;

    public string $respondent_name = '';

    public string $respondent_role = '';

    /** @var array<int, array{outdoor_sun: int, outdoor_shade: int, indoor: int}> */
    public array $answers = [];

    public function mount(): void
    {
        foreach (array_keys(config('survey_scenarios')) as $id) {
            $this->answers[$id] = [
                'outdoor_sun' => 50,
                'outdoor_shade' => 50,
                'indoor' => 100,
            ];
        }
    }

    public function next(): void
    {
        if ($this->step === 0) {
            $this->validate([
                'respondent_name' => 'nullable|string|max:120',
                'respondent_role' => 'nullable|string|max:120',
            ]);
        } else {
            $id = $this->currentScenarioId();
            $this->validate([
                "answers.$id.outdoor_sun" => 'required|integer|min:0|max:100',
                "answers.$id.outdoor_shade" => 'required|integer|min:0|max:100',
                "answers.$id.indoor" => 'required|integer|min:0|max:100',
            ]);
        }

        if ($this->step < 10) {
            $this->step++;
        }
    }

    public function back(): void
    {
        if ($this->step > 0) {
            $this->step--;
        }
    }

    public function save(): void
    {
        foreach ($this->answers as $id => $vals) {
            $this->validate([
                "answers.$id.outdoor_sun" => 'required|integer|min:0|max:100',
                "answers.$id.outdoor_shade" => 'required|integer|min:0|max:100',
                "answers.$id.indoor" => 'required|integer|min:0|max:100',
            ]);
        }

        $response = SurveyResponse::query()->create([
            'respondent_name' => $this->respondent_name ?: null,
            'respondent_role' => $this->respondent_role ?: null,
        ]);

        foreach ($this->answers as $scenarioId => $vals) {
            SurveyAnswer::query()->create([
                'survey_response_id' => $response->id,
                'scenario_id' => $scenarioId,
                'outdoor_sun' => $vals['outdoor_sun'],
                'outdoor_shade' => $vals['outdoor_shade'],
                'indoor' => $vals['indoor'],
            ]);
        }

        session()->flash('status', 'Umfrage gespeichert. Danke!');

        $this->redirect(route('survey.results'), navigate: true);
    }

    public function currentScenarioId(): ?int
    {
        if ($this->step < 1 || $this->step > 10) {
            return null;
        }

        return $this->step;
    }

    public function render()
    {
        return view('livewire.survey-form', [
            'scenarios' => config('survey_scenarios'),
            'title' => 'Umfrage',
        ]);
    }
}
