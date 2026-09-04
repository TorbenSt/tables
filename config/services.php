<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'xai' => [
        'key' => env('XAI_API_KEY'),
        'base_url' => env('XAI_BASE_URL', 'https://api.x.ai/v1'),
        'model' => env('XAI_MODEL', 'grok-4.6'),
        'vision_model' => env('XAI_VISION_MODEL', 'grok-4.20-0309-non-reasoning'),
        'timeout' => (int) env('XAI_TIMEOUT', 300),
    ],

    'open_meteo' => [
        'base_url' => env('OPEN_METEO_URL', 'https://api.open-meteo.com/v1'),
        'timezone' => env('OPEN_METEO_TIMEZONE', 'Europe/Berlin'),
    ],

    'nominatim' => [
        'base_url' => env('NOMINATIM_URL', 'https://nominatim.openstreetmap.org'),
        'user_agent' => env('NOMINATIM_USER_AGENT', 'TablesPoC/1.0 (https://localhost)'),
    ],

    'overpass' => [
        'url' => env('OVERPASS_URL', 'https://overpass-api.de/api/interpreter'),
        'fallbacks' => array_values(array_filter(array_map('trim', explode(',', (string) env(
            'OVERPASS_FALLBACKS',
            'https://overpass.kumi.systems/api/interpreter'
        ))))),
        'radius_m' => (int) env('OVERPASS_RADIUS_M', 80),
        'timeout' => (int) env('OVERPASS_TIMEOUT', 18),
    ],

    'map_imagery' => [
        'url' => env('MAP_IMAGERY_URL', 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'),
        'attribution' => env('MAP_IMAGERY_ATTR', 'Tiles © Esri — Esri, Maxar, Earthstar Geographics'),
        'max_zoom' => (int) env('MAP_IMAGERY_MAX_ZOOM', 19),
    ],

];
