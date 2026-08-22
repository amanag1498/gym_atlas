<?php

use App\Services\Events\EventService;
use App\Services\Notification\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('chat:prune')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('communications:dispatch-outbox')
    ->everyMinute()
    ->name('communication-outbox-dispatch')
    ->withoutOverlapping();
Schedule::command('communications:dispatch-campaigns')
    ->everyMinute()
    ->name('scheduled-communication-campaigns')
    ->withoutOverlapping();
Schedule::command('communications:dispatch-announcements')
    ->everyMinute()
    ->name('scheduled-announcement-dispatch')
    ->withoutOverlapping();
Schedule::command('communications:dispatch-webhooks')
    ->everyMinute()
    ->name('whatsapp-webhook-recovery')
    ->withoutOverlapping();
Schedule::command('notifications:prune-fcm-tokens')
    ->dailyAt('03:30')
    ->name('stale-firebase-token-pruning')
    ->withoutOverlapping();
Schedule::command('memberships:reconcile-lifecycle')
    ->dailyAt('00:10')
    ->name('membership-lifecycle-reconciliation')
    ->withoutOverlapping();
Schedule::call(fn () => app(ReminderService::class)->runDueReminders())
    ->dailyAt('09:00')
    ->name('membership-and-attendance-email-reminders')
    ->withoutOverlapping();
Schedule::call(fn () => app(EventService::class)->runDueReminders())
    ->everyMinute()
    ->name('event-lifecycle-and-reminders')
    ->withoutOverlapping();
