<?php

// config for Mis3085/TursoLaravel
return [
    'client' => [
        'connect_timeout' => env('TURSO_CONNECT_TIMEOUT', 2),
        'timeout' => env('TURSO_REQUEST_TIMEOUT', 5),
    ],

    'sync_command' => [
        'node_path' => env('NODE_PATH_EXECUTABLE', '/usr/bin/node'), // Full path to the node executable. E.g: /usr/bin/node
        'script_filename' => 'turso-sync.mjs',
        'script_path' => realpath(__DIR__ . '/..'),
        'timeout' => 60,
    ],
];
