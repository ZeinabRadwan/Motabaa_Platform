<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    /*
    | CORS_ALLOWED_ORIGINS: comma-separated list (production). Falls back to FRONT_URL, then *.
    */
    'allowed_origins' => (static function (): array {
        $configured = env('CORS_ALLOWED_ORIGINS');
        if (is_string($configured) && trim($configured) !== '') {
            return array_values(array_filter(array_map(
                static fn (string $origin): string => rtrim(trim($origin), '/'),
                explode(',', $configured),
            )));
        }

        $front = env('FRONT_URL');
        if (is_string($front) && trim($front) !== '') {
            return [rtrim(trim($front), '/')];
        }

        return ['*'];
    })(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,

    'supports_credentials' => false,

];
