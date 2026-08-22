<?php

namespace App\Enums;

enum NotificationTransport: string
{
    case Database = 'database';
    case Realtime = 'realtime';
    case Firebase = 'fcm';
    case WhatsApp = 'whatsapp';
}
