<?php

return [
    'max_request_bytes' => (int) env('MAX_REQUEST_BYTES', 1048576),

    'headers' => [
        'content_security_policy' => env(
            'CONTENT_SECURITY_POLICY',
            "default-src 'none'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'"
        ),
        'permissions_policy' => env(
            'PERMISSIONS_POLICY',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()'
        ),
    ],
];
