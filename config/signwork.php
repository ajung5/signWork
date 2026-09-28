<?php

return [
    'libreoffice' => env('SIGNWORK_LIBREOFFICE', 'soffice'),
    'conversion_timeout' => (int) env('SIGNWORK_CONVERSION_TIMEOUT', 120),
    // Public demo credential; never use a real BSrE passphrase in mock mode.
    'mock_passphrase' => 'MOCK-SIGNWORK-2026',
    'provider' => env('SIGNWORK_PROVIDER', 'mock'),
    'python' => env('SIGNWORK_PYTHON', 'python3'),
    'timeout' => (int) env('SIGNWORK_PDF_TIMEOUT', 120),
    'max_upload_kb' => 5120,
    'verification_base_url' => env('SIGNWORK_VERIFY_URL', env('APP_URL', 'http://localhost')),
    'bsre' => [
        'base_url' => env('SIGNWORK_BSRE_BASE_URL'),
        'client_id' => env('SIGNWORK_BSRE_CLIENT_ID'),
        'client_secret' => env('SIGNWORK_BSRE_CLIENT_SECRET'),
    ],
];
