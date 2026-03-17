<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WallBit Encryption Key
    |--------------------------------------------------------------------------
    |
    | 32-byte hex-encoded key used for AES-256-GCM encryption of WallBit API
    | keys at rest. TREAT AS IMMUTABLE once data exists in wallbit_keys table.
    | Rotating this key requires re-encrypting all stored keys.
    |
    */
    'encryption_key' => env('WALLBIT_ENCRYPTION_KEY'),

    /*
    |--------------------------------------------------------------------------
    | WallBit API Base URL
    |--------------------------------------------------------------------------
    */
    'api_base_url' => env('WALLBIT_API_BASE_URL', 'https://api.wallbit.io/api/public/v1'),
];
