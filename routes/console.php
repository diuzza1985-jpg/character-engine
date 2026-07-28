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
Schedule::command('character-drafts:prune-expired')->daily();
// Formato "tema_caldo" (documento temi-attualità, sez. 3): "ogni poche ore" per restare
// ragionevoli sul piano gratuito Reddit (100 richieste/minuto) e sul costo delle chiamate LLM
// di giudizio — non serve una cadenza più fitta, un tema "caldo" resta valido per giorni
// (CheckHotTopics::MAX_TOPIC_AGE_DAYS).
Schedule::command('character:check-hot-topics')->everyFourHours();
