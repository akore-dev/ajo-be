<?php

return [
    'public_key' => env('PAYSTACK_PUBLIC_KEY'),
    'secret_key' => env('PAYSTACK_SECRET_KEY'),
    'webhook_secret' => env('PAYSTACK_WEBHOOK_SECRET'),

    'currency' => 'NGN',

    'base_url' => 'https://api.paystack.co',

    'webhook_timeout' => 10,

    'retry_attempts' => 3,
    'retry_delay' => 1000,
];
