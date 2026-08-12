<?php

use App\Services\Notification\ReminderService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('chat:prune')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('memberships:reconcile-lifecycle')
    ->dailyAt('00:10')
    ->name('membership-lifecycle-reconciliation')
    ->withoutOverlapping();
Schedule::call(fn () => app(ReminderService::class)->runDueReminders())
    ->dailyAt('09:00')
    ->name('membership-and-attendance-email-reminders')
    ->withoutOverlapping();
