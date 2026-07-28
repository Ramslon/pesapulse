<?php

return [

    'api_key' => env('RESEND_API_KEY'),

    'from' => env('MAIL_FROM_ADDRESS', 'onboarding@resend.dev'),

    'from_name' => env('MAIL_FROM_NAME', 'PesaPulse'),

];