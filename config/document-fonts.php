<?php

return [
    'disk' => 'local',
    'directory' => 'document-fonts/files',
    'package_directory' => resource_path('fonts/documents'),
    'bundled_directory' => 'document-fonts/bundled',
    'runtime_directory' => 'document-fonts/runtime',
    'system_config' => '/etc/fonts/fonts.conf',
    'max_upload_kb' => 10240,
    'timeout_seconds' => 20,
    'preview_renderer' => 'docx-template:blocks-v1:fonts-v1',
    'executables' => ['list' => '/usr/bin/fc-list', 'scan' => '/usr/bin/fc-scan', 'cache' => '/usr/bin/fc-cache', 'version' => '/usr/bin/libreoffice'],
    'fallbacks' => ['Arial', 'Calibri', 'Liberation Sans'],
];
