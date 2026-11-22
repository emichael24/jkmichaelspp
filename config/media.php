<?php

return [
    'require_scan' => env('MEDIA_REQUIRE_SCAN', env('APP_ENV') === 'production'),
    'scanner' => env('MEDIA_SCANNER', 'clamscan'),
    'max_bytes' => 500 * 1024 * 1024,
    'scorm_max_entries' => 5000,
    'scorm_max_expanded_bytes' => 1024 * 1024 * 1024,
];
