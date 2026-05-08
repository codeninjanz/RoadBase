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
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'maps_browser_key' => env('GOOGLE_MAPS_BROWSER_KEY'),
        'maps_server_key' => env('GOOGLE_MAPS_SERVER_KEY'),
        'places_key' => env('GOOGLE_PLACES_KEY'),
    ],

    'nzta_tms' => [
        'url' => env('NZTA_TMS_FEATURE_URL'),
    ],
    'nzta_aadt' => [
        'sites_url' => env('NZTA_AADT_FEATURE_URL'),
        'lines_url' => env('NZTA_AADT_LINES_URL'),
    ],
    'nslr' => [
        'url' => env('NSLR_FEATURE_URL'),
    ],
    'cas' => [
        'url' => env('CAS_FEATURE_URL'),
    ],
    'stats_nz' => [
        'ta_url' => env('STATS_NZ_TA_URL'),
    ],
    'nz_roads' => [
        'centrelines_url' => env('NZ_ROADS_CENTRELINES_URL'),
    ],

];
