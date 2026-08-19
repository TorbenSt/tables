<?php

namespace App\Services\Decisions;

use App\Models\DecisionEntry;
use App\Models\DecisionRun;
use App\Models\Venue;
use App\Models\WeatherSnapshot;
use App\Services\Weather\OpenMeteoClient;
use App\Services\Xai\GrokClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class DecisionEngine
{
    public function __construct(
        private OpenMeteoClient $weather,
        private SurveyAggregator $aggregator,
        private ScenarioMatcher $matcher,
        private GrokClient $grok,
    ) {}

    public function recompute(Venue $venue, bool $useGrok = true): DecisionRun
    {
        $payload = $this->weather->forecast($venue, 14);
        $hash = hash('sha256', json_encode($payload));

        $snapshot = WeatherSnapshot::query()->create([
            'venue_id' => $venue->id,
            'hash' => $hash,
            'payload' => $payload,
            'fetched_at' => now(),
        ]);

        $days = $this->weather->daysFromPayload($payload);
        $profile = $this->aggregator->profile();
        $source = 'rules';
        $notes = null;

        $entries = [];
        foreach ($days as $day) {
            $scenarioId = $this->matcher->match($day);
            $releases = $profile[$scenarioId] ?? $this->aggregator->defaultProfile()[$scenarioId];

            $entry = [
                'target_date' => $day['date'],
                'matched_scenario_id' => $scenarioId,
                'outdoor_sun' => (int) round($releases['outdoor_sun']),
                'outdoor_shade' => (int) round($releases['outdoor_shade']),
                'indoor' => (int) round($releases['indoor']),
                'weather_day' => $day,
                'reasoning' => sprintf(
                    'Regelbasiert: Szenario %d (%s) gematcht. Temp max %.1f°C, Niederschlag %.1f mm, Bewölkung %s%%.',
                    $scenarioId,
                    config("survey_scenarios.$scenarioId.title", 'unbekannt'),
                    $day['temp_max'],
                    $day['precipitation_sum'],
                    $day['avg_cloudcover'] ?? 'n/a'
                ),
                'grok_adjusted' => false,
            ];

            $entries[] = $entry;
        }

        if ($useGrok && $this->grok->isConfigured()) {
            try {
                $adjusted = $this->adjustWithGrok($entries, $profile);
                if ($adjusted !== null) {
                    $entries = $adjusted;
                    $source = 'hybrid';
                    $notes = 'Regeln + Grok-Feinabstimmung';
                }
            } catch (Throwable $e) {
                Log::warning('Grok decision adjust failed', ['error' => $e->getMessage()]);
                $notes = 'Grok fehlgeschlagen, reine Regeln: '.$e->getMessage();
            }
        } elseif ($useGrok && ! $this->grok->isConfigured()) {
            $notes = 'Kein XAI_API_KEY – reine Regel-Engine';
        }

        $run = DecisionRun::query()->create([
            'venue_id' => $venue->id,
            'weather_snapshot_id' => $snapshot->id,
            'source' => $source,
            'ran_at' => now(),
            'notes' => $notes,
        ]);

        foreach ($entries as $entry) {
            DecisionEntry::query()->create([
                'decision_run_id' => $run->id,
                ...$entry,
            ]);
        }

        return $run->load('entries');
    }

    /**
     * @param  array<int, array<string, mixed>>  $entries
     * @param  array<int, array<string, mixed>>  $profile
     * @return array<int, array<string, mixed>>|null
     */
    private function adjustWithGrok(array $entries, array $profile): ?array
    {
        $compact = array_map(fn ($e) => [
            'date' => $e['target_date'],
            'matched_scenario_id' => $e['matched_scenario_id'],
            'releases' => [
                'outdoor_sun' => $e['outdoor_sun'],
                'outdoor_shade' => $e['outdoor_shade'],
                'indoor' => $e['indoor'],
            ],
            'weather' => [
                'temp_max' => $e['weather_day']['temp_max'] ?? null,
                'precipitation_sum' => $e['weather_day']['precipitation_sum'] ?? null,
                'morning_rain_mm' => $e['weather_day']['morning_rain_mm'] ?? null,
                'afternoon_rain_mm' => $e['weather_day']['afternoon_rain_mm'] ?? null,
                'avg_cloudcover' => $e['weather_day']['avg_cloudcover'] ?? null,
                'windspeed_max' => $e['weather_day']['windspeed_max'] ?? null,
            ],
            'rule_reasoning' => $e['reasoning'],
        ], $entries);

        $system = <<<'SYS'
Du bist Entscheidungshilfe für eine Gastro-Reservierungsplanung.
Du erhältst regelbasierte Freigabeprozente (outdoor_sun, outdoor_shade, indoor) aus einer Betreiber-Umfrage und Tageswetter.
Passe die Prozente nur leicht an (max. ±15 Punkte), wenn das Wetter Grenzfälle oder asymmetrisches Regen-Timing nahelegt.
Behalte matched_scenario_id bei, außer ein offensichtlicher Fehlmatch liegt vor.
Antworte als JSON-Objekt: {"days":[{"date":"YYYY-MM-DD","matched_scenario_id":1,"releases":{"outdoor_sun":0,"outdoor_shade":0,"indoor":100},"reasoning":"..."}]}
SYS;

        $user = json_encode([
            'survey_profile' => $profile,
            'rule_decisions' => $compact,
        ], JSON_UNESCAPED_UNICODE);

        $result = $this->grok->chatJson($system, (string) $user);
        $days = $result['days'] ?? null;
        if (! is_array($days)) {
            return null;
        }

        $byDate = [];
        foreach ($days as $day) {
            if (! isset($day['date'])) {
                continue;
            }
            $byDate[$day['date']] = $day;
        }

        foreach ($entries as &$entry) {
            $adj = $byDate[$entry['target_date']] ?? null;
            if (! $adj) {
                continue;
            }
            $releases = $adj['releases'] ?? [];
            foreach (['outdoor_sun', 'outdoor_shade', 'indoor'] as $key) {
                if (isset($releases[$key])) {
                    $entry[$key] = max(0, min(100, (int) round($releases[$key])));
                }
            }
            if (isset($adj['matched_scenario_id'])) {
                $entry['matched_scenario_id'] = (int) $adj['matched_scenario_id'];
            }
            if (! empty($adj['reasoning'])) {
                $entry['reasoning'] = (string) $adj['reasoning'];
            }
            $entry['grok_adjusted'] = true;
        }
        unset($entry);

        return $entries;
    }
}
