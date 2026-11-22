<?php

return [
    'restore_evidence' => env('BACKUP_RESTORE_EVIDENCE'),
    'scanner_health_file' => env('SCANNER_HEALTH_FILE', '/var/lib/jkmichaels/clamav/updated_at'),
    'document_sandbox_evidence' => env('DOCUMENT_SANDBOX_EVIDENCE', '/var/lib/jkmichaels/document-sandbox-health.json'),
];
