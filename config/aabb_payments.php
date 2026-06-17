<?php

return [
    'driver' => env('AABB_PAYMENT_DRIVER', 'manual'),
    'manual_reference_prefix' => env('AABB_PAYMENT_MANUAL_REFERENCE_PREFIX', 'AABB-MANUAL'),
    'allowed_methods' => [
        'QR App AABB',
        'Boleto Banco do Brasil',
        'Debito em conta Banco do Brasil',
        'Cartao presencial',
    ],
];
