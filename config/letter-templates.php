<?php

return [
    'disk' => 'local',
    'paths' => [
        'sources' => 'letter-templates/sources',
        'temporary' => 'letter-templates/temporary',
        'previews' => 'letter-templates/previews',
    ],
    'max_upload_kb' => 10240,
    'zip' => [
        'max_entries' => 1000,
        'max_uncompressed_bytes' => 50 * 1024 * 1024,
        'max_xml_bytes' => 2 * 1024 * 1024,
        'max_xml_total_bytes' => 8 * 1024 * 1024,
        'max_compression_ratio' => 200,
    ],
    'placeholders' => ['max_length' => 100, 'max_occurrences' => 1000],
];
