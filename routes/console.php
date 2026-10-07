<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('payments:expire')->everyMinute()->withoutOverlapping();
Schedule::command('payments:reconcile')->everyFiveMinutes()->withoutOverlapping();
Schedule::command('webhooks:dispatch-due')->everyMinute()->withoutOverlapping();
