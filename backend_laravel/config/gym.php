<?php

return [
    'platform_admin_emails' => array_values(array_filter(array_map(
        static fn (string $value): string => trim($value),
        explode(',', (string) env('PLATFORM_ADMIN_EMAILS', 'amanagarwal1498@gmail.com')),
    ))),

    'seeded_admin_password' => env('SEEDED_ADMIN_PASSWORD', 'wZ7xR2ru@123'),
    'demo_gym_owner_email' => env('DEMO_GYM_OWNER_EMAIL', 'amanag1498@gmail.com'),
    'demo_user_password' => env('DEMO_USER_PASSWORD', 'wZ7xR2ru@123'),
];
