<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Chat Message Retention
    |--------------------------------------------------------------------------
    |
    | Messages are kept for this many days before the scheduled prune command
    | removes them. Set to 0 to disable automatic pruning.
    |
    */
    'message_retention_days' => (int) env('CHAT_MESSAGE_RETENTION_DAYS', 365),

    'messages_per_page' => (int) env('CHAT_MESSAGES_PER_PAGE', 80),
    'max_messages_per_page' => (int) env('CHAT_MAX_MESSAGES_PER_PAGE', 100),
];
