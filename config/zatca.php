<?php

return [
    /*
    |--------------------------------------------------------------------------
    | ZATCA Integration Mode
    |--------------------------------------------------------------------------
    |
    | Choose which phase(s) to enable:
    | - 'phase_1'  : QR code generation only (no API calls)
    | - 'phase_2'  : Full clearance & reporting via FATOORA API
    | - 'both'     : Enable both phases simultaneously
    |
    */
    'phase' => env('ZATCA_PHASE', 'both'),

    /*
    |--------------------------------------------------------------------------
    | Environment
    |--------------------------------------------------------------------------
    |
    | 'sandbox'    : Development & testing (developer portal)
    | 'production' : Live FATOORA platform
    |
    */
    'environment' => env('ZATCA_ENVIRONMENT', 'sandbox'),

    /*
    |--------------------------------------------------------------------------
    | API Endpoints
    |--------------------------------------------------------------------------
    |
    | ZATCA FATOORA API endpoints. Override via env if needed.
    |
    */
    'api' => [
        'sandbox' => [
            'base'    => env('ZATCA_SANDBOX_API_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing/developer-portal'),
            'version' => env('ZATCA_API_VERSION', 'V2'),
        ],
        'production' => [
            'base'    => env('ZATCA_PRODUCTION_API_URL', 'https://gw-fatoora.zatca.gov.sa/e-invoicing'),
            'version' => env('ZATCA_API_VERSION', 'V2'),
        ],
        'timeout' => env('ZATCA_API_TIMEOUT', 60),
    ],
    'api_middleware' => env('ZATCA_API_MIDDLEWARE', 'api'),

    /*
    |--------------------------------------------------------------------------
    | EGS (ERP/Government System) Defaults
    |--------------------------------------------------------------------------
    |
    | Default EGS information used across all invoices.
    | These can be overridden at runtime per invoice.
    |
    */
    'egs' => [
        'uuid'          => env('ZATCA_EGS_UUID', ''),
        'vat_number'    => env('ZATCA_VAT_NUMBER', ''),
        'vat_name'      => env('ZATCA_VAT_NAME', ''),
        'crn_number'    => env('ZATCA_CRN_NUMBER', ''),
        'industry'      => env('ZATCA_INDUSTRY', 'Retail'),
        'city'          => env('ZATCA_CITY', 'Riyadh'),
        'city_subdivision' => env('ZATCA_CITY_SUBDIVISION', ''),
        'street'        => env('ZATCA_STREET', ''),
        'building'      => env('ZATCA_BUILDING', '0000'),
        'plot_id'       => env('ZATCA_PLOT_ID', '0000'),
        'postal_zone'   => env('ZATCA_POSTAL_ZONE', '00000'),
        'branch_name'   => env('ZATCA_BRANCH_NAME', 'Main Branch'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue Configuration
    |--------------------------------------------------------------------------
    |
    | Queue connection and job settings for async ZATCA operations.
    |
    */
    /*
    |--------------------------------------------------------------------------
    | ZATCA Credentials
    |--------------------------------------------------------------------------
    |
    | Compliance certificate, private key, and secret obtained from onboarding.
    | These can also be set via environment variables.
    |
    */
    'certificate' => env('ZATCA_CERTIFICATE'),
    'private_key' => env('ZATCA_PRIVATE_KEY'),
    'secret' => env('ZATCA_SECRET'),

    'queue' => [
        'connection' => env('ZATCA_QUEUE_CONNECTION', 'sync'),
        'queue'      => env('ZATCA_QUEUE_NAME', 'zatca'),
        'tries'      => env('ZATCA_QUEUE_TRIES', 3),
        'timeout'    => env('ZATCA_QUEUE_TIMEOUT', 120),
        'retry_delay_minutes' => env('ZATCA_RETRY_DELAY_MINUTES', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure ZATCA-specific logging behaviour.
    |
    */
    'logging' => [
        'enabled' => env('ZATCA_LOGGING_ENABLED', true),
        'channel' => env('ZATCA_LOG_CHANNEL', 'stack'),
        'level'   => env('ZATCA_LOG_LEVEL', 'info'),
        'mask_pii' => env('ZATCA_LOG_MASK_PII', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | QR Code
    |--------------------------------------------------------------------------
    |
    | QR code display configuration for Phase 1.
    |
    */
    'qr_code' => [
        'size'         => env('ZATCA_QR_SIZE', 200),
        'renderer'     => env('ZATCA_QR_RENDERER', 'svg'),
        'logo_path'    => env('ZATCA_QR_LOGO_PATH', ''),
        'margin'       => env('ZATCA_QR_MARGIN', 10),
        'error_correction' => env('ZATCA_QR_ERROR_CORRECTION', 'high'),
    ],
];
