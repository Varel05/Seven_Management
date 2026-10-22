<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'webhook' => [
        'secret' => env('WEBHOOK_SECRET'),
    ],

    'n8n' => [
        'custom_suit_webhook_url' => env('N8N_CUSTOM_SUIT_WEBHOOK_URL', 'http://localhost:5678/webhook/custom-suit-ai'),
    ],

    'karyawan' => [
        'api_url' => env('KARYAWAN_API_URL', 'http://localhost:8080'),
        'api_key' => env('KARYAWAN_API_KEY', 'kunci-rahasia-seven-2026'),
        'timeout' => (int) env('KARYAWAN_API_TIMEOUT', 15),
    ],

    'sales_app' => [
        'url' => env('SALES_APP_URL', 'https://postcard-hardened-legroom.ngrok-free.dev'),
        'token' => env('SALES_APP_TOKEN', 'jap_d70fadaf3f7abb5b46f860f9979af23aec0b0f84'),
        'timeout' => (int) env('SALES_APP_TIMEOUT', 15),
    ],

    'telegram' => [
        'owner_ids' => array_filter(array_map('trim', explode(',', env('TELEGRAM_OWNER_IDS', env('TELEGRAM_ADMIN_IDS', ''))))),
        'akuntan_ids' => array_filter(array_map('trim', explode(',', env('TELEGRAM_AKUNTAN_IDS', '')))),
        'owner_phones' => array_filter(array_map('trim', explode(',', env('TELEGRAM_OWNER_PHONES', env('TELEGRAM_ADMIN_PHONES', ''))))),
        'akuntan_phones' => array_filter(array_map('trim', explode(',', env('TELEGRAM_AKUNTAN_PHONES', '')))),
    ],

];
