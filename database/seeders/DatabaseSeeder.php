<?php

namespace Database\Seeders;

use App\Models\SurveyAnswer;
use App\Models\SurveyResponse;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Demo Admin',
            'email' => 'demo@tables.test',
        ]);

        Venue::query()->create([
            'name' => 'Café Sonnenseite (Demo)',
            'latitude' => 52.520008,
            'longitude' => 13.404954,
            'tables_indoor' => 24,
            'tables_outdoor_sun' => 12,
            'tables_outdoor_shade' => 10,
            'timezone' => 'Europe/Berlin',
        ]);

        // Seed one sample survey response with plausible percentages
        $response = SurveyResponse::query()->create([
            'respondent_name' => 'Demo Betreiber',
            'respondent_role' => 'Inhaber',
        ]);

        $sample = [
            1 => [35, 90, 100],
            2 => [85, 95, 100],
            3 => [25, 55, 100],
            4 => [65, 85, 100],
            5 => [15, 70, 100],
            6 => [5, 25, 100],
            7 => [0, 0, 100],
            8 => [25, 45, 100],
            9 => [45, 55, 100],
            10 => [100, 100, 100],
        ];

        foreach ($sample as $scenarioId => [$sun, $shade, $indoor]) {
            SurveyAnswer::query()->create([
                'survey_response_id' => $response->id,
                'scenario_id' => $scenarioId,
                'outdoor_sun' => $sun,
                'outdoor_shade' => $shade,
                'indoor' => $indoor,
            ]);
        }

        $this->call(DemoPhotoSessionSeeder::class);
    }
}
