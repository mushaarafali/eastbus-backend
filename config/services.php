<?php

return [
    'gemini' => [
        'key' => env('GEMINI_API_KEY'),
        'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'mailjet' => [
        'key' => env('MAILJET_API_KEY'),
        'secret' => env('MAILJET_SECRET_KEY'),
    ],
];