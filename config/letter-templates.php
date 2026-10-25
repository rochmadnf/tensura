<?php

return [
    'disk' => 'local',
    'paths' => [
        'sources' => 'letter-templates/sources',
        'temporary' => 'letter-templates/temporary',
        'previews' => 'letter-templates/previews',
    ],
    'max_upload_kb' => 20480,
    'zip' => [
        'max_entries' => 1000,
        'max_uncompressed_bytes' => 50 * 1024 * 1024,
        'max_xml_bytes' => 2 * 1024 * 1024,
        'max_xml_total_bytes' => 8 * 1024 * 1024,
        'max_compression_ratio' => 200,
    ],
    'test' => [
        'short_text_max' => 255,
        'long_text_max' => 10000,
        'max_rows' => 100,
        'max_total_rows' => 500,
        'timeout_seconds' => 30,
        'expires_hours' => 24,
        'max_output_bytes' => 20 * 1024 * 1024,
    ],
    'placeholders' => ['max_length' => 100, 'max_occurrences' => 1000],
];
