<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Services\Notification\ReminderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('chat:prune')->dailyAt('03:15')->withoutOverlapping();
Schedule::call(fn () => app(ReminderService::class)->runDueReminders())
    ->dailyAt('09:00')
    ->name('membership-and-attendance-email-reminders')
    ->withoutOverlapping();
