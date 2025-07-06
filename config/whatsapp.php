<?php

return [
    'default' => 'service',
    'channels' => [
        // For 24-Hour Unconfirmed Reminders
        'operations' => [
            'host' => env('WHATSAPP_API_HOST', 'http://127.0.0.1'),
            'port' => env('WHATSAPP_OPERATIONS_PORT', 3000),
        ],
        // For Confirmed Reminders, Feedback & Marketing
        'service' => [
            'host' => env('WHATSAPP_API_HOST', 'http://127.0.0.1'),
            'port' => env('WHATSAPP_SERVICE_PORT', 3001),
        ],
    ],
];