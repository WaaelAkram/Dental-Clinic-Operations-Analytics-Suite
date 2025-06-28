<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Feedback Request Timings
    |--------------------------------------------------------------------------
    */
    // Start looking for appointments that ended this many hours ago.
    'delay_hours_start' => 1,
    // Stop looking for appointments that ended this many hours ago.
    'delay_hours_end' => 2,

    /*
    |--------------------------------------------------------------------------
    | Feedback Request Template
    |--------------------------------------------------------------------------
    */
    'template' => env(
        'FEEDBACK_TEMPLATE',
        "مرحبا {patient_name}، نتمنى ان زيارتكم لدى {doctor_name} كانت ممتازة. يهمنا معرفة رأيكم وملاحظاتكم عبر الرابط: {feedback_link}"
    ),

    'resend_cooldown_months' => 4,

    'feedback_url' => env('FEEDBACK_URL', 'https://example.com/clinic-feedback-link'),
];