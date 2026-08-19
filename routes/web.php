<?php

use App\Livewire\DecisionCalendar;
use App\Livewire\DecisionTimeline;
use App\Livewire\PhotoSessionForm;
use App\Livewire\PhotoSessionIndex;
use App\Livewire\PhotoSessionShow;
use App\Livewire\SurveyForm;
use App\Livewire\SurveyResults;
use App\Models\DetectedTable;
use App\Models\DecisionRun;
use App\Models\PhotoSession;
use App\Models\SurveyResponse;
use App\Models\Venue;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    $venue = Venue::query()->first();

    return view('dashboard', [
        'title' => 'Dashboard',
        'venue' => $venue,
        'surveyCount' => SurveyResponse::query()->count(),
        'decisionRuns' => DecisionRun::query()->count(),
        'photoSessions' => PhotoSession::query()->count(),
        'detectedTables' => DetectedTable::query()->count(),
    ]);
})->name('dashboard');

Route::get('/survey', SurveyForm::class)->name('survey');
Route::get('/survey/results', SurveyResults::class)->name('survey.results');

Route::get('/venues/{venue}/decisions', DecisionCalendar::class)->name('venues.decisions');
Route::get('/venues/{venue}/decisions/timeline', DecisionTimeline::class)->name('venues.timeline');

Route::get('/photo-sessions', PhotoSessionIndex::class)->name('photo-sessions.index');
Route::get('/photo-sessions/create', PhotoSessionForm::class)->name('photo-sessions.create');
Route::get('/photo-sessions/{session}', PhotoSessionShow::class)->name('photo-sessions.show');
