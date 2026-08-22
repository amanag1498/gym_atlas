<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'google' => [
        'client_ids' => array_values(array_filter(array_map(
            static fn (string $value): string => trim($value),
            explode(',', (string) env('GOOGLE_CLIENT_IDS', '')),
        ))),
        'certs_url' => env('GOOGLE_CERTS_URL', 'https://www.googleapis.com/oauth2/v1/certs'),
    ],

    'firebase' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'certs_url' => env(
            'FIREBASE_CERTS_URL',
            'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com',
        ),
        'service_account_path' => env('FIREBASE_SERVICE_ACCOUNT_PATH', env('GOOGLE_APPLICATION_CREDENTIALS')),
        'service_account_json' => env('FIREBASE_SERVICE_ACCOUNT_JSON'),
        'web_api_key' => env('FIREBASE_WEB_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'web_app_id' => env('FIREBASE_WEB_APP_ID'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET'),
        'token_stale_days' => (int) env('FIREBASE_TOKEN_STALE_DAYS', 60),
    ],

    'realtime' => [
        'url' => env('REALTIME_SERVER_URL', ''),
        'internal_api_key' => env('SOCKET_INTERNAL_API_KEY', ''),
    ],

    'public_whatsapp' => [
        'number' => env('PUBLIC_WHATSAPP_NUMBER', '917451008842'),
    ],

    'meta_whatsapp' => [
        'graph_url' => env('META_GRAPH_URL', 'https://graph.facebook.com'),
        'graph_version' => env('META_GRAPH_VERSION'),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'embedded_signup_config_id' => env('META_WHATSAPP_EMBEDDED_SIGNUP_CONFIG_ID'),
        'webhook_verify_token' => env('META_WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'default_country_code' => env('WHATSAPP_DEFAULT_COUNTRY_CODE', '91'),
        'messages_per_minute' => (int) env('WHATSAPP_MESSAGES_PER_MINUTE', 60),
        'quiet_hours_start' => env('WHATSAPP_QUIET_HOURS_START', '21:00'),
        'quiet_hours_end' => env('WHATSAPP_QUIET_HOURS_END', '08:00'),
        'platform_timezone' => env('WHATSAPP_PLATFORM_TIMEZONE', 'Asia/Kolkata'),
    ],

];
