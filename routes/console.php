<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('instagram:reply-comments sofia')->hourly();
Schedule::command('schedule:materialize-weekly')->dailyAt('00:05');
Schedule::command('schedule:process-due')->everyFifteenMinutes();
// Orario da tarare in seguito (dopo che sappiamo quando girano generazione/pubblicazione).
Schedule::command('character:live-tick')->daily();
