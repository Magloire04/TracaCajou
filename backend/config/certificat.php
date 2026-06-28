<?php

return [
    'private_key_path' => env('CERT_SIGNING_PRIVATE_KEY_PATH'),
    'public_key_path'  => env('CERT_SIGNING_PUBLIC_KEY_PATH'),
    'verify_base_url'  => env('CERT_PUBLIC_VERIFY_BASE_URL', 'http://localhost:8000'),
];
