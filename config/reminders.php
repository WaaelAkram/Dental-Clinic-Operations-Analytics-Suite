

<?php

return [
    'windows' => [
        // For Confirmed (status 1) appointments, send reminder 60 minutes before.
        1 => env('REMINDER_WINDOW_CONFIRMED_MINUTES', 120),

        // For Unconfirmed (status 0) appointments, send reminder 120 minutes before.
        0 => env('REMINDER_WINDOW_UNCONFIRMED_MINUTES', 240),
    ],

   // Message template for CONFIRMED appointments.
    'template_confirmed' => env(
        'REMINDER_TEMPLATE_CONFIRMED',
        "مرحبا {patient_name},\n\nنريد تذكيركم بالموعد لدى عيادتنا عند{doctor_name} اليوم الساعة {appointment_time}.\n\nشكرا لكم"
    ),

    // Message template for UNCONFIRMED appointments.
    'template_unconfirmed' => env(
        'REMINDER_TEMPLATE_UNCONFIRMED',
        "مرحبا {patient_name},\n\nموعدك لدى عيادتنا عند  {doctor_name} اليوم الساعة {appointment_time}. يرجى تاكيد الموعد عبر هذه المحادثة او الاتصال.\n\nشكرا لكم."
    ),
];