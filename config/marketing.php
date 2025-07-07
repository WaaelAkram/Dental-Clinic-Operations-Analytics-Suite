<?php
return [
    'daily_limit' => env('MARKETING_DAILY_LIMIT', 5),
    'send_window_start' => '15:00',
    'send_window_end'   => '21:30',
    'lapsed_patient_template' => env('MARKETING_LAPSED_PATIENT_TEMPLATE', "مرحباً {patient_name}، لقد اشتقنا لك في مركزنا! لم نرك منذ عام. هل تود حجز موعد فحص دوري؟ نحن هنا لخدمتك."),
    'lapsed_patient_image_url' => env('MARKETING_LAPSED_PATIENT_IMAGE_URL', null), // <-- ADD THIS LINE
    'conversion_window_days' => env('MARKETING_CONVERSION_WINDOW_DAYS', 90),
    'marketing_cooldown_days' => env('MARKETING_COOLDOWN_DAYS', 365),
];