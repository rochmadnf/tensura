<?php

return [
    'disk' => 'local',
    'paths' => ['previews' => 'letters/previews', 'staging' => 'letters/staging', 'official' => 'letters/official', 'pdfs' => 'letters/pdfs'],
    'pdf' => ['timeout' => 60, 'max_bytes' => 30 * 1024 * 1024, 'profile_revision' => 'libreoffice-pdf-v1'],
    'cleanup' => ['orphan_hours' => 24],
    'health' => ['cache_store' => env('LETTERS_HEALTH_CACHE_STORE', 'database'), 'worker_seconds' => 120, 'scheduler_seconds' => 180],
    'preview_hours' => 24,
    'max_output_bytes' => 20 * 1024 * 1024,
    'render_timeout' => 30,
    'job_timeout' => 90,
    'lease_seconds' => 110,
    'max_attempts' => 3,
    'sse' => ['channel' => 'letter_operations', 'seconds' => 60, 'heartbeat_seconds' => 15, 'history_days' => 7],
];
