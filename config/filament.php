<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Broadcasting
    |--------------------------------------------------------------------------
    |
    | By configuring the Laravel Echo configuration, Filament connects
    | to your Reverb / Pusher-compatible WebSockets server.
    |
    */

    'broadcasting' => [
        'echo' => [
            'broadcaster' => 'reverb',
            'key' => env('VITE_REVERB_APP_KEY'),
            'wsHost' => env('VITE_REVERB_HOST', 'localhost'),
            'wsPort' => (int) env('VITE_REVERB_PORT', 8080),
            'wssPort' => (int) env('VITE_REVERB_PORT', 8080),
            'authEndpoint' => '/broadcasting/auth',
            'forceTLS' => env('VITE_REVERB_SCHEME', 'http') === 'https',
            'enabledTransports' => ['ws', 'wss'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    */

    'default_filesystem_disk' => env('FILESYSTEM_DISK', 'local'),

];
