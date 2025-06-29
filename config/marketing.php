<?php

// config/marketing.php

return [
    /**
     * The maximum number of marketing messages to send per day.
     */
    'daily_limit' => env('MARKETING_DAILY_LIMIT', 50),

    /**
     * The time window during which messages will be sent.
     * Use 'H:i' format.
     */
    'send_window_start' => '15:00', // 3:00 PM
    'send_window_end'   => '21:30', // 9:30 PM

    /**
     * The WhatsApp message template for lapsed patients.
     * {patient_name} will be replaced with the patient's actual name.
     */
    'lapsed_patient_template' => "مرحباً {patient_name}، لقد اشتقنا لك في مركزنا! لم نرك منذ عام. هل تود حجز موعد فحص دوري؟ نحن هنا لخدمتك.",

    /**
     * The number of days after sending a message to still attribute a
     * returning appointment as a conversion for this campaign.
     */
    'conversion_window_days' => env('MARKETING_CONVERSION_WINDOW_DAYS', 90),

    /**
     * The minimum number of days to wait before a patient can be selected
     * for another marketing message after being part of a batch.
     */
    'marketing_cooldown_days' => env('MARKETING_COOLDOWN_DAYS', 365),
];