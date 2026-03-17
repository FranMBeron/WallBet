<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('leagues:update-status')->everyFiveMinutes();
Schedule::command('snapshots:capture')->hourly();
Schedule::command('demo:reset')->dailyAt('03:00');
