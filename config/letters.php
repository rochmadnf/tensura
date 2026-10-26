<?php

return [
    'disk' => 'local',
    'paths' => ['previews' => 'letters/previews', 'staging' => 'letters/staging', 'official' => 'letters/official'],
    'preview_hours' => 24,
    'max_output_bytes' => 20 * 1024 * 1024,
    'render_timeout' => 30,
    'job_timeout' => 90,
    'lease_seconds' => 110,
    'max_attempts' => 3,
    'sse' => ['channel' => 'letter_operations', 'seconds' => 60, 'heartbeat_seconds' => 15, 'history_days' => 7],
];
