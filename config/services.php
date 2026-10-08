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

    'resend' => [
        'key' => env('RESEND_KEY'),
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

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('APP_URL') . '/auth/google/callback', // Menggunakan APP_URL dari .env
    ],

    // WebAPI BPS: sumber tabel statistik & publikasi (dipakai App\Services\Bps\BpsApiClient).
    'bps' => [
        'key' => env('BPS_API_KEY'),
        'url' => env('BPS_API_URL', 'https://webapi.bps.go.id/v1/api'),
        'domain' => env('BPS_DOMAIN', '1273'),                    // 1273 = Kota Pematangsiantar
        'cache_menit' => (int) env('BPS_CACHE_MENIT', 360),       // lama respons API disimpan di cache
        'timeout' => (int) env('BPS_TIMEOUT', 25),                // detik per permintaan
    ],

];
