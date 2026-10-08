<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:expire')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('webhooks:dispatch-due')->everyMinute()->withoutOverlapping();

// Shared hosting (e.g. cPanel) without Supervisor: let cron drain the queue every minute.
if (config('payments.queue_via_scheduler')) {
    Schedule::command('queue:work --queue='.config('payments.webhooks.queue').',default --stop-when-empty --max-time=50 --tries=1')
        ->everyMinute()
        ->withoutOverlapping(5);
}
