<?php

use App\Livewire\DecisionCalendar;
use App\Livewire\DecisionTimeline;
use App\Livewire\MapSiteEditor;
use App\Livewire\MapSiteIndex;
use App\Livewire\MapSiteShow;
use App\Livewire\PhotoSessionCamera;
use App\Livewire\PhotoSessionForm;
use App\Livewire\PhotoSessionIndex;
use App\Livewire\PhotoSessionShow;
use App\Livewire\SurveyForm;
use App\Livewire\SurveyResults;
use App\Models\DecisionRun;
use App\Models\MapSite;
use App\Models\MapTable;
use App\Models\SurveyResponse;
use App\Models\Venue;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    $venue = Venue::query()->first();

    return view('dashboard', [
        'title' => 'Dashboard',
        'venue' => $venue,
        'surveyCount' => SurveyResponse::query()->count(),
        'decisionRuns' => DecisionRun::query()->count(),
        'mapSites' => MapSite::query()->count(),
        'mapTables' => MapTable::query()->count(),
    ]);
})->name('dashboard');

Route::get('/survey', SurveyForm::class)->name('survey');
Route::get('/survey/results', SurveyResults::class)->name('survey.results');

Route::get('/venues/{venue}/decisions', DecisionCalendar::class)->name('venues.decisions');
Route::get('/venues/{venue}/decisions/timeline', DecisionTimeline::class)->name('venues.timeline');

Route::get('/map-sites', MapSiteIndex::class)->name('map-sites.index');
Route::get('/map-sites/create', MapSiteEditor::class)->name('map-sites.create');
Route::get('/map-sites/{site}', MapSiteShow::class)->name('map-sites.show');

Route::get('/photo-sessions', PhotoSessionIndex::class)->name('photo-sessions.index');
Route::view('/photo-sessions/create', 'photo-sessions.choose')->name('photo-sessions.create');
Route::get('/photo-sessions/create/gallery', PhotoSessionForm::class)->name('photo-sessions.gallery');
Route::get('/photo-sessions/create/camera', PhotoSessionCamera::class)->name('photo-sessions.camera');
Route::get('/photo-sessions/{session}/camera', PhotoSessionCamera::class)->name('photo-sessions.camera.continue');
Route::get('/photo-sessions/{session}', PhotoSessionShow::class)->name('photo-sessions.show');

Route::get('/media/{path}', function (string $path) {
    $path = str_replace('..', '', $path);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path);
})->where('path', '.*')->name('media');
