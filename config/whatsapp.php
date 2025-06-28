<?php

return [
    /*
    |--------------------------------------------------------------------------
    | WhatsApp API Service Configuration
    |--------------------------------------------------------------------------
    |
    | This is the configuration for the Node.js service that runs the
    | whatsapp-web.js client. The Laravel app will send HTTP requests
    | to this service to send messages.
    |
    */

    'api_host' => env('WHATSAPP_API_HOST', 'http://127.0.0.1'),
    'api_port' => env('WHATSAPP_API_PORT', 3000),

];