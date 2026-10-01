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
        'base_url' => rtrim((string) env('SIGNWORK_BSRE_BASE_URL', 'http://192.168.18.26'), '/'),
        'sign_path' => env('SIGNWORK_BSRE_SIGN_PATH', '/api/sign/pdf'),
        'download_path' => env('SIGNWORK_BSRE_DOWNLOAD_PATH', '/api/sign/download/{id}'),
        'basic_username' => env('SIGNWORK_BSRE_BASIC_USERNAME', env('SIGNWORK_BSRE_CLIENT_ID')),
        'basic_password' => env('SIGNWORK_BSRE_BASIC_PASSWORD', env('SIGNWORK_BSRE_CLIENT_SECRET')),
        'bearer_token' => env('SIGNWORK_BSRE_BEARER_TOKEN'),
        'response_type' => env('SIGNWORK_BSRE_RESPONSE_TYPE'),
        'tampilan' => env('SIGNWORK_BSRE_TAMPILAN', 'invisible'),
        'image' => filter_var(env('SIGNWORK_BSRE_IMAGE', 'false'), FILTER_VALIDATE_BOOLEAN),
        // linkQR dibentuk per dokumen pada saat signing, bukan dari .env.
        'x_axis' => (int) env('SIGNWORK_BSRE_X_AXIS', 3),
        'y_axis' => (int) env('SIGNWORK_BSRE_Y_AXIS', 4),
        'width' => (int) env('SIGNWORK_BSRE_WIDTH', 80),
        'height' => (int) env('SIGNWORK_BSRE_HEIGHT', 80),
        'timeout' => (int) env('SIGNWORK_BSRE_TIMEOUT', 120),
    ],
];
