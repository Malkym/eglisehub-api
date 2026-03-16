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

    'allowed_origins' => [
        'http://localhost:5173',  // Vue.js dev server (Vite)
        'http://localhost:3000',  // Alternative
        'http://127.0.0.1:5173',  // Alternative avec 127.0.0.1
        'http://127.0.0.1:3000',  // Alternative avec 127.0.0.1
    ],

    'allowed_origins_patterns' => [
        // Accepter tous les sous-domaines eglisehub en production
        '#^https?://(.+\.)?eglisehub\.org$#',
        '#^https?://(.+\.)?eglisehub\.com$#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];