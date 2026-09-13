<?php

return [

    'admin_password' => env('ADMIN_PWD'),

    'file_system_key' => env('LARAVEL_FILE_SYSTEM_KEY'),

    'dev' => [
        'phone' => env('DEV_PHONE'),
        'phone_2' => env('DEV2_PHONE'),
        'email' => env('DEV_EMAIL'),
    ],

    'currency' => [
        'en' => env('EN_CURRENCY'),
        'ar' => env('AR_CURRENCY'),
    ],

    'files' => [
        // Historical env key spelling is intentional.
        'storage' => env('UPLAOD_FILE_STORAGE'),
        'region' => env('UPLAOD_REGION'),
    ],

    'bunny' => [
        'base_hostname' => env('BUNNYCDN_BASE_HOSTNAME'),
        'storage_zone' => env('BUNNYCDN_STORAGE_ZONE'),
        'api_key' => env('BUNNYCDN_API_KEY'),
        'access_key' => env('BUNNYCDN_ACCESS_KEY'),
        'token_key' => env('BUNNYCDN_TOKEN_KEY'),
        'url' => env('BUNNYCDN_URL'),
        'pull_zone_id' => env('BUNNYCDN_PULL_ZONE_ID'),
    ],

    'whatsapp' => [
        'sid' => env('WHATS_APP_SID'),
        'token' => env('WHATS_APP_TOKEN'),
        'phone' => env('WHATS_APP_PHONE'),
        'service_sid' => env('WHATS_APP_SERVICE_SID'),
        'content_sid' => env('WHATS_APP_CONTENT_SID'),
        'center_name' => env('CENTER_NAME'),
    ],

];
